<?php
/** Physical handover of an approved claim. One transaction: handover record + claim + report + histories + notifications. */
final class HandoverService
{
    /** Approved claims waiting to be collected, oldest approval first. */
    public static function awaiting()
    {
        return db_all("SELECT q.id, q.claimant_id, q.reviewed_at, q.created_at, r.id AS report_id, r.item_name, r.location, r.image_path, "
            . "c.name AS category_name, u.full_name AS claimant_name, u.email AS claimant_email "
            . "FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = q.claimant_id "
            . "WHERE q.status = 'approved' ORDER BY q.reviewed_at ASC, q.id ASC");
    }

    public static function recent($limit)
    {
        return db_all('SELECT h.id, h.claim_id, h.handed_over_at, h.handover_notes, r.id AS report_id, r.item_name, u.full_name AS recipient_name, s.full_name AS staff_name '
            . 'FROM handover_records h JOIN claim_requests q ON q.id = h.claim_id JOIN item_reports r ON r.id = q.item_report_id '
            . 'JOIN users u ON u.id = h.recipient_id JOIN users s ON s.id = h.confirmed_by ORDER BY h.handed_over_at DESC, h.id DESC LIMIT ' . (int) $limit);
    }

    /** Items genuinely returned (handover record present and both statuses are "returned"). */
    public static function returnedCount()
    {
        return db_count("SELECT COUNT(*) FROM handover_records h JOIN claim_requests q ON q.id = h.claim_id JOIN item_reports r ON r.id = q.item_report_id WHERE q.status = 'returned' AND r.status = 'returned'");
    }

    /** $handedOverLocal is a datetime-local value typed in Manila time. Returns array('ok', 'errors', 'handover_id'). */
    public static function confirm($staff, $claimId, $recipientId, $handedOverLocal, $notes, $identityConfirmed)
    {
        if (!is_staff_user($staff)) {
            return array('ok' => false, 'errors' => array('Only staff can confirm a handover.'));
        }
        $errors = array();
        $notes = clean_text($notes);
        if (!$identityConfirmed) {
            $errors[] = 'Confirm that you checked the recipient\'s identity before handing the item over.';
        }
        $when = local_input_to_utc($handedOverLocal);
        if ($when === null) {
            $errors[] = 'Enter a valid handover date and time.';
        } elseif (utc_ts($when) > time() + 300) {
            $errors[] = 'The handover time cannot be in the future.';
        }
        if (text_len($notes) > 1000) {
            $errors[] = 'Handover notes must be 1,000 characters or fewer.';
        }
        if (count($errors) > 0) {
            return array('ok' => false, 'errors' => $errors);
        }
        try {
            $handoverId = db_transaction(function ($pdo) use ($staff, $claimId, $recipientId, $when, $notes) {
                $claim = ClaimService::find($claimId);
                if (!$claim) {
                    throw new BusinessRuleException('That claim could not be found.');
                }
                if ($claim['status'] === 'returned' || db_count('SELECT COUNT(*) FROM handover_records WHERE claim_id = ?', array((int) $claim['id'])) > 0) {
                    throw new BusinessRuleException('This item has already been handed over.');
                }
                if ($claim['status'] !== 'approved') {
                    throw new BusinessRuleException('Only approved claims can be handed over. This claim is ' . strtolower(status_label($claim['status'])) . '.');
                }
                if ((int) $recipientId !== (int) $claim['claimant_id']) {
                    throw new BusinessRuleException('The item can only be handed to the approved claimant.');
                }
                if (!in_array($claim['report_status'], array('claim_pending', 'open'), true)) {
                    throw new BusinessRuleException('The item report is ' . strtolower(status_label($claim['report_status'])) . ', so it cannot be handed over.');
                }
                $pdo->prepare('INSERT INTO handover_records (claim_id, recipient_id, confirmed_by, handed_over_at, handover_notes) VALUES (?, ?, ?, ?, ?)')
                    ->execute(array((int) $claim['id'], (int) $claim['claimant_id'], (int) $staff['id'], $when, $notes === '' ? null : $notes));
                $id = (int) $pdo->lastInsertId();
                ClaimService::markReturned($claim, $staff['id'], 'Item handed over to the claimant' . ($notes !== '' ? ': ' . $notes : ''));
                ReportService::setStatus($claim['item_report_id'], 'returned', $staff['id'], 'Item returned to its owner (claim #' . (int) $claim['id'] . ')');
                foreach (db_all("SELECT id FROM claim_requests WHERE item_report_id = ? AND id <> ? AND status IN ('pending','under_review','needs_info')", array((int) $claim['item_report_id'], (int) $claim['id'])) as $other) {
                    ClaimService::autoReject((int) $other['id'], $staff['id'], 'The item was returned to another verified claimant.');
                }
                NotificationService::create((int) $claim['claimant_id'], 'handover_confirmed', 'Handover confirmed: "' . $claim['item_name'] . '" was returned to you on ' . fmt_dt($when) . '.', 'claim', (int) $claim['id']);
                if ((int) $claim['reporter_id'] !== (int) $claim['claimant_id']) {
                    NotificationService::create((int) $claim['reporter_id'], 'report_returned', 'The item you reported ("' . $claim['item_name'] . '") was returned to its owner. Thank you for helping!', 'report', (int) $claim['item_report_id']);
                }
                return $id;
            });
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
        return array('ok' => true, 'errors' => array(), 'handover_id' => $handoverId);
    }
}
