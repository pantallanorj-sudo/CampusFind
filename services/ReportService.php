<?php
/** Item reports: create, edit, withdraw (soft delete), status changes and listing queries. */
final class ReportService
{
    public static function find($id)
    {
        return db_one('SELECT r.*, c.name AS category_name, u.full_name AS reporter_name, u.email AS reporter_email '
            . 'FROM item_reports r JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = r.user_id WHERE r.id = ?', array((int) $id));
    }

    /**
     * Filters: scope (browse|mine|staff), user_id, q, type, category_id, status, location, date_from, date_to, sort.
     * 'browse' is the public listing (open + claim_pending only).
     */
    public static function search($f, $page, $perPage)
    {
        $where = array();
        $params = array();
        $scope = isset($f['scope']) ? $f['scope'] : 'browse';
        if ($scope === 'browse') {
            $where[] = "r.status IN ('open','claim_pending')";
        } elseif ($scope === 'mine') {
            $where[] = 'r.user_id = ?';
            $params[] = (int) $f['user_id'];
        }
        if (!empty($f['status']) && in_array($f['status'], REPORT_STATUSES, true)) {
            $where[] = 'r.status = ?';
            $params[] = $f['status'];
        } elseif ($scope === 'staff') {
            $where[] = "r.status <> 'archived'";
        }
        if (!empty($f['type']) && in_array($f['type'], array('lost', 'found'), true)) {
            $where[] = 'r.report_type = ?';
            $params[] = $f['type'];
        }
        if (!empty($f['category_id'])) {
            $where[] = 'r.category_id = ?';
            $params[] = (int) $f['category_id'];
        }
        if (!empty($f['q'])) {
            $like = like_pattern($f['q']);
            $where[] = "(r.item_name LIKE ? ESCAPE '!' OR r.description LIKE ? ESCAPE '!' OR r.location LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!')";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($f['location'])) {
            $where[] = "r.location LIKE ? ESCAPE '!'";
            $params[] = like_pattern($f['location']);
        }
        if (!empty($f['date_from']) && valid_date($f['date_from'])) {
            $where[] = 'r.date_occurred >= ?';
            $params[] = $f['date_from'];
        }
        if (!empty($f['date_to']) && valid_date($f['date_to'])) {
            $where[] = 'r.date_occurred <= ?';
            $params[] = $f['date_to'];
        }
        $sorts = array(
            'newest' => 'r.created_at DESC, r.id DESC',
            'oldest' => 'r.created_at ASC, r.id ASC',
            'name' => 'r.item_name COLLATE NOCASE ASC, r.id ASC',
            'date' => 'r.date_occurred DESC, r.id DESC',
        );
        $sort = (isset($f['sort']) && is_string($f['sort']) && isset($sorts[$f['sort']])) ? $f['sort'] : 'newest';
        $whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
        $from = 'FROM item_reports r JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = r.user_id ';
        $total = db_count('SELECT COUNT(*) ' . $from . $whereSql, $params);
        $pg = paginate($total, $perPage, $page);
        $sql = 'SELECT r.*, c.name AS category_name, u.full_name AS reporter_name, '
            . "(SELECT COUNT(*) FROM claim_requests q WHERE q.item_report_id = r.id AND q.status IN ('pending','under_review','needs_info','approved')) AS active_claims "
            . $from . $whereSql . ' ORDER BY ' . $sorts[$sort] . ' LIMIT ' . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'];
        return array('rows' => db_all($sql, $params), 'pg' => $pg);
    }

    public static function history($reportId)
    {
        return HistoryService::forReport($reportId);
    }

    public static function claimsFor($reportId)
    {
        return db_all('SELECT q.id, q.status, q.created_at, u.full_name AS claimant_name FROM claim_requests q JOIN users u ON u.id = q.claimant_id '
            . 'WHERE q.item_report_id = ? ORDER BY q.created_at ASC, q.id ASC', array((int) $reportId));
    }

    /** Change a report's status and write status_history. Call inside a transaction. Returns true if it changed. */
    public static function setStatus($reportId, $newStatus, $actorId, $notes)
    {
        if (!in_array($newStatus, REPORT_STATUSES, true)) {
            throw new InvalidArgumentException('Unknown report status.');
        }
        $row = db_one('SELECT status FROM item_reports WHERE id = ?', array((int) $reportId));
        if (!$row) {
            throw new BusinessRuleException('That report could not be found.');
        }
        if ($row['status'] === $newStatus) {
            return false;
        }
        db()->prepare('UPDATE item_reports SET status = ?, updated_at = ? WHERE id = ?')->execute(array($newStatus, now_utc(), (int) $reportId));
        HistoryService::item($reportId, $actorId, $row['status'], $newStatus, $notes);
        return true;
    }

    /** $photoFile: one $_FILES-style entry or null. Returns array('ok'=>true,'id'=>n) | array('ok'=>false,'errors'=>[...]) | array('ok'=>false,'duplicates'=>[...]). */
    public static function create($user, $input, $photoFile, $ackDuplicates)
    {
        $v = validate_report_input($input, true);
        $clean = $v[0];
        $errors = $v[1];
        if ($clean['category_id'] > 0 && !CategoryService::exists($clean['category_id'])) {
            $errors[] = 'The selected category does not exist.';
        }
        $photo = null;
        if ($photoFile !== null) {
            $photo = ImageUploadService::inspect($photoFile);
            if (!$photo['ok']) {
                $errors[] = $photo['error'];
                $photo = null;
            }
        }
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        if (!$ackDuplicates) {
            $dups = DuplicateDetectionService::find($clean['report_type'], $clean['category_id'], $clean['item_name'], $clean['location'], $clean['date_occurred']);
            if (count($dups) > 0) {
                return array('ok' => false, 'errors' => array(), 'duplicates' => $dups);
            }
        }
        $stored = null;
        try {
            if ($photo !== null) {
                $stored = ImageUploadService::store($photo, 'items');
            }
            $id = db_transaction(function ($pdo) use ($user, $clean, $stored) {
                $now = now_utc();
                $pdo->prepare('INSERT INTO item_reports (user_id, category_id, report_type, item_name, description, location, date_occurred, image_path, status, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute(array((int) $user['id'], $clean['category_id'], $clean['report_type'], $clean['item_name'], $clean['description'],
                        $clean['location'], $clean['date_occurred'], $stored, 'open', $now, $now));
                $newId = (int) $pdo->lastInsertId();
                if ($stored !== null) {
                    $pdo->prepare('INSERT INTO item_images (item_report_id, uploaded_by, image_path, created_at) VALUES (?, ?, ?, ?)')
                        ->execute(array($newId, (int) $user['id'], $stored, $now));
                }
                HistoryService::item($newId, $user['id'], null, 'open', 'Report created');
                return $newId;
            });
        } catch (Throwable $e) {
            if ($stored !== null) {
                ImageUploadService::delete($stored);
            }
            throw $e;
        }
        return array('ok' => true, 'errors' => array(), 'id' => $id);
    }

    /** Edit permitted fields (owner while untouched, or staff). The report type is never editable. */
    public static function update($actor, $reportId, $input, $photoFile, $removePhoto)
    {
        $report = self::find($reportId);
        if (!$report) {
            return array('ok' => false, 'errors' => array('That report could not be found.'));
        }
        $check = report_edit_check($actor, $report);
        if (!$check[0]) {
            return array('ok' => false, 'errors' => array($check[1]));
        }
        $v = validate_report_input($input, false);
        $clean = $v[0];
        $errors = $v[1];
        if ($clean['category_id'] > 0 && !CategoryService::exists($clean['category_id'])) {
            $errors[] = 'The selected category does not exist.';
        }
        $photo = null;
        if ($photoFile !== null) {
            $photo = ImageUploadService::inspect($photoFile);
            if (!$photo['ok']) {
                $errors[] = $photo['error'];
                $photo = null;
            }
        }
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        $labels = array('category_id' => 'category', 'item_name' => 'item name', 'description' => 'description', 'location' => 'location', 'date_occurred' => 'date');
        $changed = array();
        foreach ($labels as $col => $label) {
            if ((string) $report[$col] !== (string) $clean[$col]) {
                $changed[] = $label;
            }
        }
        $oldImage = $report['image_path'];
        $hadImage = ($oldImage !== null && $oldImage !== '');
        if ($photo !== null) {
            $changed[] = 'photo';
        } elseif ($removePhoto && $hadImage) {
            $changed[] = 'photo removed';
        }
        if (count($changed) === 0) {
            return array('ok' => true, 'errors' => array(), 'changed' => array());
        }
        $stored = null;
        try {
            if ($photo !== null) {
                $stored = ImageUploadService::store($photo, 'items');
            }
            db_transaction(function ($pdo) use ($actor, $report, $clean, $stored, $removePhoto, $changed) {
                $now = now_utc();
                $image = $report['image_path'];
                if ($stored !== null) {
                    $image = $stored;
                } elseif ($removePhoto) {
                    $image = null;
                }
                $pdo->prepare('UPDATE item_reports SET category_id = ?, item_name = ?, description = ?, location = ?, date_occurred = ?, image_path = ?, updated_at = ? WHERE id = ?')
                    ->execute(array($clean['category_id'], $clean['item_name'], $clean['description'], $clean['location'], $clean['date_occurred'], $image, $now, (int) $report['id']));
                if ($stored !== null || $removePhoto) {
                    $pdo->prepare('DELETE FROM item_images WHERE item_report_id = ?')->execute(array((int) $report['id']));
                }
                if ($stored !== null) {
                    $pdo->prepare('INSERT INTO item_images (item_report_id, uploaded_by, image_path, created_at) VALUES (?, ?, ?, ?)')
                        ->execute(array((int) $report['id'], (int) $actor['id'], $stored, $now));
                }
                $isOwner = ((int) $report['user_id'] === (int) $actor['id']);
                HistoryService::item($report['id'], $actor['id'], $report['status'], $report['status'],
                    'Details edited by ' . ($isOwner ? 'the reporter' : 'staff') . ': ' . implode(', ', $changed));
                if (!$isOwner) {
                    NotificationService::create((int) $report['user_id'], 'report_updated', 'Staff updated the details of your report "' . $report['item_name'] . '".', 'report', (int) $report['id']);
                }
            });
        } catch (Throwable $e) {
            if ($stored !== null) {
                ImageUploadService::delete($stored);
            }
            throw $e;
        }
        if (($stored !== null || $removePhoto) && $hadImage) {
            ImageUploadService::delete($oldImage);
        }
        return array('ok' => true, 'errors' => array(), 'changed' => $changed);
    }

    /**
     * Owner "delete": a controlled closure. The row, photo and audit history are kept; the report leaves the listings.
     * Only open reports with no claim history can be removed this way.
     */
    public static function withdraw($actor, $reportId)
    {
        try {
            db_transaction(function ($pdo) use ($actor, $reportId) {
                $report = ReportService::find($reportId);
                if (!$report) {
                    throw new BusinessRuleException('That report could not be found.');
                }
                $check = report_withdraw_check($actor, $report);
                if (!$check[0]) {
                    throw new BusinessRuleException($check[1]);
                }
                ReportService::setStatus($report['id'], 'closed', $actor['id'], 'Deleted by the reporter (kept as a closed record)');
            });
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
        return array('ok' => true, 'errors' => array());
    }

    /** Statuses staff may move this report to by hand (claims, handover and archiving have their own flows). */
    public static function manualTransitions($report)
    {
        $active = report_claim_count($report['id'], ACTIVE_CLAIM_STATUSES);
        if ($report['status'] === 'open' && $active === 0) {
            return array('closed');
        }
        if ($report['status'] === 'claim_pending' && $active === 0) {
            return array('open', 'closed');
        }
        if ($report['status'] === 'closed') {
            return array('open');
        }
        return array();
    }

    public static function changeStatus($actor, $reportId, $newStatus, $notes)
    {
        if (!is_staff_user($actor)) {
            return array('ok' => false, 'errors' => array('Only staff can change a report\'s status.'));
        }
        $notes = clean_text($notes);
        if (text_len($notes) > 500) {
            return array('ok' => false, 'errors' => array('Notes must be 500 characters or fewer.'));
        }
        try {
            db_transaction(function ($pdo) use ($actor, $reportId, $newStatus, $notes) {
                $report = ReportService::find($reportId);
                if (!$report) {
                    throw new BusinessRuleException('That report could not be found.');
                }
                if (!in_array($newStatus, ReportService::manualTransitions($report), true)) {
                    throw new BusinessRuleException('A ' . strtolower(status_label($report['status'])) . ' report cannot be changed to ' . strtolower(status_label($newStatus)) . ' right now.');
                }
                if ($newStatus === 'closed' && text_len($notes) < 3) {
                    throw new BusinessRuleException('Add a short reason when closing a report.');
                }
                ReportService::setStatus($report['id'], $newStatus, $actor['id'], $notes !== '' ? $notes : null);
                if ((int) $report['user_id'] !== (int) $actor['id']) {
                    NotificationService::create((int) $report['user_id'], 'report_status', 'Your report "' . $report['item_name'] . '" is now ' . strtolower(status_label($newStatus)) . '.', 'report', (int) $report['id']);
                }
            });
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
        return array('ok' => true, 'errors' => array());
    }
}
