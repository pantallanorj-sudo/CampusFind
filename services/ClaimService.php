<?php
/**
 * Claim workflow:  pending -> under_review <-> needs_info -> approved -> returned (via HandoverService)
 *                  plus rejected (staff) and cancelled (claimant).
 * Every public method returns array('ok' => bool, 'errors' => [...]) and never trusts a status coming from a form.
 */
final class ClaimService
{
    private static function transitions()
    {
        return array(
            'pending' => array('under_review', 'rejected', 'cancelled'),
            'under_review' => array('needs_info', 'approved', 'rejected', 'cancelled'),
            'needs_info' => array('under_review', 'rejected', 'cancelled'),
            'approved' => array('returned', 'rejected', 'cancelled'),
            'rejected' => array(),
            'returned' => array(),
            'cancelled' => array(),
        );
    }

    public static function canTransition($from, $to)
    {
        $map = self::transitions();
        return isset($map[$from]) && in_array($to, $map[$from], true);
    }

    /* ---------- queries ---------- */

    public static function find($id)
    {
        return db_one('SELECT q.*, r.item_name, r.report_type, r.status AS report_status, r.user_id AS reporter_id, r.location, r.date_occurred, '
            . 'r.description AS item_description, r.image_path, r.category_id, c.name AS category_name, '
            . 'u.full_name AS claimant_name, u.email AS claimant_email, rv.full_name AS reviewer_name '
            . 'FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id JOIN categories c ON c.id = r.category_id '
            . 'JOIN users u ON u.id = q.claimant_id LEFT JOIN users rv ON rv.id = q.reviewed_by WHERE q.id = ?', array((int) $id));
    }

    public static function evidence($claimId)
    {
        return db_all('SELECT ev.*, rv.full_name AS reviewer_name FROM claim_evidence ev LEFT JOIN users rv ON rv.id = ev.reviewed_by WHERE ev.claim_id = ? ORDER BY ev.id ASC', array((int) $claimId));
    }

    public static function history($claimId)
    {
        return HistoryService::forClaim($claimId);
    }

    /** The latest message a staff member wrote for the claimant (needs info / rejected / approved). */
    public static function latestFeedback($claimId)
    {
        return db_one("SELECT h.notes, h.changed_at, h.new_status FROM claim_status_history h JOIN users u ON u.id = h.changed_by "
            . "WHERE h.claim_id = ? AND u.role IN ('staff','admin') AND h.new_status IN ('needs_info','rejected','approved') AND h.notes IS NOT NULL AND h.notes <> '' "
            . "ORDER BY h.id DESC LIMIT 1", array((int) $claimId));
    }

    public static function listForClaimant($userId)
    {
        return db_all('SELECT q.*, r.item_name, r.report_type, r.status AS report_status, r.image_path, c.name AS category_name '
            . 'FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id JOIN categories c ON c.id = r.category_id '
            . 'WHERE q.claimant_id = ? ORDER BY q.created_at DESC, q.id DESC', array((int) $userId));
    }

    /** Staff queue. Filters: status (a claim status or "active"), q, category_id, date_from, date_to, sort (newest|oldest). */
    public static function queue($f, $page, $perPage)
    {
        $where = array('1 = 1');
        $params = array();
        $status = isset($f['status']) ? $f['status'] : '';
        if ($status === 'active') {
            $where[] = "q.status IN ('pending','under_review','needs_info')";
        } elseif (in_array($status, CLAIM_STATUSES, true)) {
            $where[] = 'q.status = ?';
            $params[] = $status;
        }
        if (!empty($f['q'])) {
            $like = like_pattern($f['q']);
            $where[] = "(r.item_name LIKE ? ESCAPE '!' OR u.full_name LIKE ? ESCAPE '!')";
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($f['category_id'])) {
            $where[] = 'r.category_id = ?';
            $params[] = (int) $f['category_id'];
        }
        $b = local_date_bounds(isset($f['date_from']) ? $f['date_from'] : '', isset($f['date_to']) ? $f['date_to'] : '');
        if ($b[0] !== null) {
            $where[] = 'q.created_at >= ?';
            $params[] = $b[0];
        }
        if ($b[1] !== null) {
            $where[] = 'q.created_at < ?';
            $params[] = $b[1];
        }
        $order = (isset($f['sort']) && $f['sort'] === 'oldest') ? 'q.created_at ASC, q.id ASC' : 'q.created_at DESC, q.id DESC';
        $from = 'FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = q.claimant_id ';
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = db_count('SELECT COUNT(*) ' . $from . $whereSql, $params);
        $pg = paginate($total, $perPage, $page);
        $rows = db_all('SELECT q.id, q.status, q.created_at, q.updated_at, q.reviewed_at, q.item_report_id, r.item_name, r.report_type, r.status AS report_status, '
            . 'c.name AS category_name, u.full_name AS claimant_name, (SELECT COUNT(*) FROM claim_evidence ev WHERE ev.claim_id = q.id) AS evidence_count '
            . $from . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'], $params);
        return array('rows' => $rows, 'pg' => $pg);
    }

    /** array(allowed, reason): may this student claim this report? */
    public static function eligibility($user, $report)
    {
        if (!$user || $user['role'] !== 'student') {
            return array(false, 'Only student accounts can submit claims.');
        }
        if ($report['report_type'] !== 'found') {
            return array(false, 'Claims can only be made for found items. Lost-item reports are matched by staff.');
        }
        if ((int) $report['user_id'] === (int) $user['id']) {
            return array(false, 'You cannot claim an item that you reported yourself.');
        }
        if (!in_array($report['status'], array('open', 'claim_pending'), true)) {
            return array(false, 'This item is ' . strtolower(status_label($report['status'])) . ' and can no longer be claimed.');
        }
        if (db_count('SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND claimant_id = ?', array((int) $report['id'], (int) $user['id'])) > 0) {
            return array(false, 'You have already submitted a claim for this item.');
        }
        if (db_count("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND status IN ('approved','returned')", array((int) $report['id'])) > 0) {
            return array(false, 'This item has already been approved for another claimant.');
        }
        return array(true, '');
    }

    /* ---------- internals ---------- */

    private static function attempt($fn)
    {
        try {
            $result = db_transaction($fn);
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
        return array('ok' => true, 'errors' => array(), 'data' => $result);
    }

    private static function loadForStaff($staff, $claimId)
    {
        if (!is_staff_user($staff)) {
            throw new BusinessRuleException('Only staff can review claims.');
        }
        $claim = self::find($claimId);
        if (!$claim) {
            throw new BusinessRuleException('That claim could not be found.');
        }
        return $claim;
    }

    private static function loadForOwner($user, $claimId)
    {
        $claim = self::find($claimId);
        if (!$claim || (int) $claim['claimant_id'] !== (int) $user['id']) {
            throw new BusinessRuleException('That claim could not be found.');
        }
        return $claim;
    }

    /** Changes a claim's status (checked against the transition map), writes history and keeps the report in step. */
    private static function move($claim, $to, $actorId, $note, $byStaff)
    {
        if (!self::canTransition($claim['status'], $to)) {
            throw new BusinessRuleException('A ' . strtolower(status_label($claim['status'])) . ' claim cannot be changed to ' . strtolower(status_label($to)) . '.');
        }
        $now = now_utc();
        if ($byStaff) {
            $stmt = db()->prepare('UPDATE claim_requests SET status = ?, reviewed_by = ?, reviewed_at = ?, updated_at = ? WHERE id = ? AND status = ?');
            $stmt->execute(array($to, (int) $actorId, $now, $now, (int) $claim['id'], $claim['status']));
        } else {
            $stmt = db()->prepare('UPDATE claim_requests SET status = ?, updated_at = ? WHERE id = ? AND status = ?');
            $stmt->execute(array($to, $now, (int) $claim['id'], $claim['status']));
        }
        if ($stmt->rowCount() !== 1) {
            throw new BusinessRuleException('This claim was just changed by someone else. Reload the page and try again.');
        }
        HistoryService::claim($claim['id'], $actorId, $claim['status'], $to, $note);
        $reportId = (int) $claim['item_report_id'];
        $reportStatus = db_scalar('SELECT status FROM item_reports WHERE id = ?', array($reportId));
        if ($to === 'rejected' || $to === 'cancelled') {
            if ($reportStatus === 'claim_pending' && report_claim_count($reportId, ACTIVE_CLAIM_STATUSES) === 0) {
                ReportService::setStatus($reportId, 'open', $actorId, 'No active claims remain, so the item is open again');
            }
        } elseif ($reportStatus === 'open') {
            ReportService::setStatus($reportId, 'claim_pending', $actorId, 'A claim is being processed');
        }
    }

    private static function setInternalNotes($claimId, $notes)
    {
        if ($notes !== null) {
            db()->prepare('UPDATE claim_requests SET staff_notes = ?, updated_at = ? WHERE id = ?')
                ->execute(array($notes === '' ? null : $notes, now_utc(), (int) $claimId));
        }
    }

    private static function addEvidence($claimId, $userId, $type, $text, $storedPaths)
    {
        $now = now_utc();
        $stmt = db()->prepare('INSERT INTO claim_evidence (claim_id, user_id, evidence_type, evidence_path, evidence_description, review_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array((int) $claimId, (int) $userId, $type, null, $text, 'pending', $now));
        $n = 0;
        foreach ($storedPaths as $path) {
            $n++;
            $stmt->execute(array((int) $claimId, (int) $userId, 'photo', $path, 'Evidence photo ' . $n, 'pending', $now));
        }
    }

    /** Validate + store uploaded evidence images. Returns array(inspectedList, errors). */
    private static function inspectFiles($files)
    {
        $inspected = array();
        $errors = array();
        if (count($files) > MAX_EVIDENCE_FILES) {
            $errors[] = 'You can attach at most ' . MAX_EVIDENCE_FILES . ' evidence photos.';
            return array($inspected, $errors);
        }
        foreach ($files as $f) {
            $r = ImageUploadService::inspect($f);
            if ($r['ok']) {
                $inspected[] = $r;
            } else {
                $errors[] = $r['error'];
            }
        }
        return array($inspected, $errors);
    }

    private static function storeAll($inspected)
    {
        $stored = array();
        try {
            foreach ($inspected as $ins) {
                $stored[] = ImageUploadService::store($ins, 'claim-evidence');
            }
        } catch (Throwable $e) {
            self::discard($stored);
            throw $e;
        }
        return $stored;
    }

    private static function discard($stored)
    {
        foreach ($stored as $path) {
            ImageUploadService::delete($path);
        }
    }

    /* ---------- student actions ---------- */

    public static function submit($user, $reportId, $input, $files)
    {
        $report = ReportService::find($reportId);
        if (!$report) {
            return array('ok' => false, 'errors' => array('That item could not be found.'));
        }
        $elig = self::eligibility($user, $report);
        if (!$elig[0]) {
            return array('ok' => false, 'errors' => array($elig[1]));
        }
        $details = validate_text($input, 'claim_details', 'Ownership details', 10, 2000);
        $proof = validate_text($input, 'evidence_description', 'Proof of ownership', 10, 1000);
        $checked = self::inspectFiles($files);
        $errors = array_merge($details[1], $proof[1], $checked[1]);
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        $stored = self::storeAll($checked[0]);
        try {
            $claimId = db_transaction(function ($pdo) use ($user, $reportId, $details, $proof, $stored) {
                $fresh = ReportService::find($reportId);
                $again = ClaimService::eligibility($user, $fresh);
                if (!$again[0]) {
                    throw new BusinessRuleException($again[1]);
                }
                $now = now_utc();
                try {
                    $pdo->prepare('INSERT INTO claim_requests (item_report_id, claimant_id, claim_details, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
                        ->execute(array((int) $reportId, (int) $user['id'], $details[0], 'pending', $now, $now));
                } catch (PDOException $e) {
                    throw new BusinessRuleException('You have already submitted a claim for this item.');
                }
                $id = (int) $pdo->lastInsertId();
                HistoryService::claim($id, $user['id'], null, 'pending', 'Claim submitted');
                ClaimService::addEvidence($id, $user['id'], 'description', $proof[0], $stored);
                if ($fresh['status'] === 'open') {
                    ReportService::setStatus($reportId, 'claim_pending', $user['id'], 'A claim was submitted');
                }
                NotificationService::create((int) $user['id'], 'claim_submitted', 'Your claim for "' . $fresh['item_name'] . '" was submitted. Staff will review it soon.', 'claim', $id);
                NotificationService::create((int) $fresh['user_id'], 'report_claim_received', 'A claim was submitted for your found-item report "' . $fresh['item_name'] . '". Staff will verify ownership.', 'report', (int) $reportId);
                if (SettingsService::get('notify_staff_new_claims') === '1') {
                    NotificationService::notifyStaff('claim_new', 'New claim for "' . $fresh['item_name'] . '" is waiting for review.', 'claim', $id);
                }
                return $id;
            });
        } catch (BusinessRuleException $e) {
            self::discard($stored);
            return array('ok' => false, 'errors' => array($e->getMessage()));
        } catch (Throwable $e) {
            self::discard($stored);
            throw $e;
        }
        return array('ok' => true, 'errors' => array(), 'claim_id' => $claimId);
    }

    /** Student answers a "needs more information" request: adds evidence and puts the claim back under review. */
    public static function respond($user, $claimId, $input, $files)
    {
        $claim = self::find($claimId);
        if (!$claim || (int) $claim['claimant_id'] !== (int) $user['id']) {
            return array('ok' => false, 'errors' => array('That claim could not be found.'));
        }
        if ($claim['status'] !== 'needs_info') {
            return array('ok' => false, 'errors' => array('This claim is not waiting for more information.'));
        }
        $text = validate_text($input, 'response_text', 'Your response', 10, 2000);
        $checked = self::inspectFiles($files);
        $errors = array_merge($text[1], $checked[1]);
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        $stored = self::storeAll($checked[0]);
        try {
            $result = self::attempt(function () use ($user, $claimId, $text, $stored) {
                $c = ClaimService::loadForOwner($user, $claimId);
                if ($c['status'] !== 'needs_info') {
                    throw new BusinessRuleException('This claim is not waiting for more information.');
                }
                ClaimService::addEvidence($c['id'], $user['id'], 'additional_info', $text[0], $stored);
                ClaimService::move($c, 'under_review', $user['id'], 'Claimant provided additional information', false);
                if (!empty($c['reviewed_by'])) {
                    NotificationService::create((int) $c['reviewed_by'], 'claim_info_provided', 'The claimant added information to the claim for "' . $c['item_name'] . '".', 'claim', (int) $c['id']);
                } else {
                    NotificationService::notifyStaff('claim_info_provided', 'The claimant added information to the claim for "' . $c['item_name'] . '".', 'claim', (int) $c['id']);
                }
                return null;
            });
        } catch (Throwable $e) {
            self::discard($stored);
            throw $e;
        }
        if (!$result['ok']) {
            self::discard($stored);
        }
        return $result;
    }

    public static function cancel($user, $claimId)
    {
        return self::attempt(function () use ($user, $claimId) {
            $c = ClaimService::loadForOwner($user, $claimId);
            if (!in_array($c['status'], array('pending', 'under_review', 'needs_info', 'approved'), true)) {
                throw new BusinessRuleException('A ' . strtolower(status_label($c['status'])) . ' claim cannot be cancelled.');
            }
            ClaimService::move($c, 'cancelled', $user['id'], 'Cancelled by the claimant', false);
            if (!empty($c['reviewed_by'])) {
                NotificationService::create((int) $c['reviewed_by'], 'claim_cancelled', 'The claimant cancelled the claim for "' . $c['item_name'] . '".', 'claim', (int) $c['id']);
            }
            return null;
        });
    }

    /* ---------- staff actions ---------- */

    public static function startReview($staff, $claimId)
    {
        return self::attempt(function () use ($staff, $claimId) {
            $c = ClaimService::loadForStaff($staff, $claimId);
            if ($c['status'] !== 'pending' && $c['status'] !== 'needs_info') {
                throw new BusinessRuleException('Only pending claims can be taken into review.');
            }
            ClaimService::move($c, 'under_review', $staff['id'], $c['status'] === 'pending' ? 'Review started' : 'Review resumed', true);
            NotificationService::create((int) $c['claimant_id'], 'claim_under_review', 'Your claim for "' . $c['item_name'] . '" is now under review.', 'claim', (int) $c['id']);
            return null;
        });
    }

    public static function requestInfo($staff, $claimId, $message, $internalNotes = null)
    {
        $message = clean_text($message);
        if (text_len($message) < 5 || text_len($message) > 1000) {
            return array('ok' => false, 'errors' => array('Tell the claimant what you need (5–1,000 characters).'));
        }
        return self::attempt(function () use ($staff, $claimId, $message, $internalNotes) {
            $c = ClaimService::loadForStaff($staff, $claimId);
            if ($c['status'] !== 'under_review') {
                throw new BusinessRuleException('Start the review first; information can only be requested while a claim is under review.');
            }
            ClaimService::move($c, 'needs_info', $staff['id'], $message, true);
            ClaimService::setInternalNotes($c['id'], $internalNotes);
            NotificationService::create((int) $c['claimant_id'], 'claim_needs_info', 'Staff needs more information about your claim for "' . $c['item_name'] . '". Open the claim to respond.', 'claim', (int) $c['id']);
            return null;
        });
    }

    public static function approve($staff, $claimId, $message = '', $internalNotes = null)
    {
        $message = clean_text($message);
        if (text_len($message) > 1000) {
            return array('ok' => false, 'errors' => array('The message must be 1,000 characters or fewer.'));
        }
        return self::attempt(function () use ($staff, $claimId, $message, $internalNotes) {
            $c = ClaimService::loadForStaff($staff, $claimId);
            if ($c['status'] !== 'under_review') {
                throw new BusinessRuleException('Only a claim that is under review can be approved. Start the review first.');
            }
            if (!in_array($c['report_status'], array('open', 'claim_pending'), true)) {
                throw new BusinessRuleException('The item report is ' . strtolower(status_label($c['report_status'])) . ', so this claim cannot be approved.');
            }
            if (db_count("SELECT COUNT(*) FROM claim_requests WHERE item_report_id = ? AND status IN ('approved','returned') AND id <> ?", array((int) $c['item_report_id'], (int) $c['id'])) > 0) {
                throw new BusinessRuleException('Another claim for this item is already approved. Reject or cancel it first.');
            }
            ClaimService::move($c, 'approved', $staff['id'], $message !== '' ? $message : 'Claim approved after verification', true);
            ClaimService::setInternalNotes($c['id'], $internalNotes);
            NotificationService::create((int) $c['claimant_id'], 'claim_approved', 'Your claim for "' . $c['item_name'] . '" was approved.', 'claim', (int) $c['id']);
            NotificationService::create((int) $c['claimant_id'], 'ready_for_pickup', '"' . $c['item_name'] . '" is ready for pickup. Bring your school ID to the lost-and-found desk.', 'claim', (int) $c['id']);
            return null;
        });
    }

    public static function reject($staff, $claimId, $reason, $internalNotes = null)
    {
        $reason = clean_text($reason);
        if (text_len($reason) < 5 || text_len($reason) > 1000) {
            return array('ok' => false, 'errors' => array('Give the claimant a reason (5–1,000 characters).'));
        }
        return self::attempt(function () use ($staff, $claimId, $reason, $internalNotes) {
            $c = ClaimService::loadForStaff($staff, $claimId);
            ClaimService::move($c, 'rejected', $staff['id'], $reason, true);
            ClaimService::setInternalNotes($c['id'], $internalNotes);
            NotificationService::create((int) $c['claimant_id'], 'claim_rejected', 'Your claim for "' . $c['item_name'] . '" was not approved. Open the claim to read the staff feedback.', 'claim', (int) $c['id']);
            return null;
        });
    }

    /** Used by the handover when another claimant received the item. */
    public static function autoReject($claimId, $actorId, $reason)
    {
        $c = self::find($claimId);
        if (!$c || !in_array($c['status'], array('pending', 'under_review', 'needs_info'), true)) {
            return false;
        }
        self::move($c, 'rejected', $actorId, $reason, true);
        NotificationService::create((int) $c['claimant_id'], 'claim_rejected', 'Your claim for "' . $c['item_name'] . '" was closed because the item was returned to another verified claimant.', 'claim', (int) $c['id']);
        return true;
    }

    /** approved -> returned. Only HandoverService calls this, inside its transaction. */
    public static function markReturned($claim, $actorId, $note)
    {
        if ($claim['status'] !== 'approved') {
            throw new BusinessRuleException('Only an approved claim can be marked as returned.');
        }
        $stmt = db()->prepare("UPDATE claim_requests SET status = 'returned', updated_at = ? WHERE id = ? AND status = 'approved'");
        $stmt->execute(array(now_utc(), (int) $claim['id']));
        if ($stmt->rowCount() !== 1) {
            throw new BusinessRuleException('This claim was just changed by someone else. Reload the page and try again.');
        }
        HistoryService::claim($claim['id'], $actorId, 'approved', 'returned', $note);
    }

    public static function saveNotes($staff, $claimId, $notes)
    {
        $notes = clean_text($notes);
        if (text_len($notes) > 2000) {
            return array('ok' => false, 'errors' => array('Internal notes must be 2,000 characters or fewer.'));
        }
        return self::attempt(function () use ($staff, $claimId, $notes) {
            $c = ClaimService::loadForStaff($staff, $claimId);
            ClaimService::setInternalNotes($c['id'], $notes);
            return null;
        });
    }

    /** Staff verification of one evidence item. Returns array('ok', 'errors', 'claim_id'). */
    public static function reviewEvidence($staff, $evidenceId, $decision)
    {
        if (!is_staff_user($staff)) {
            return array('ok' => false, 'errors' => array('Only staff can verify evidence.'));
        }
        if (!in_array($decision, array('accepted', 'rejected', 'pending'), true)) {
            return array('ok' => false, 'errors' => array('Choose accepted, rejected or pending.'));
        }
        $ev = db_one('SELECT ev.id, ev.claim_id, q.status AS claim_status FROM claim_evidence ev JOIN claim_requests q ON q.id = ev.claim_id WHERE ev.id = ?', array((int) $evidenceId));
        if (!$ev) {
            return array('ok' => false, 'errors' => array('That evidence item could not be found.'));
        }
        if (!in_array($ev['claim_status'], ACTIVE_CLAIM_STATUSES, true)) {
            return array('ok' => false, 'errors' => array('Evidence can only be reviewed while the claim is still open.'), 'claim_id' => (int) $ev['claim_id']);
        }
        db()->prepare('UPDATE claim_evidence SET review_status = ?, reviewed_by = ? WHERE id = ?')->execute(array($decision, (int) $staff['id'], (int) $ev['id']));
        return array('ok' => true, 'errors' => array(), 'claim_id' => (int) $ev['claim_id']);
    }
}
