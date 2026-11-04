<?php
/**
 * Unclaimed-item retention and archiving.
 *  - The retention period always comes from system_settings (unclaimed_retention_days).
 *  - Age is counted from the report's created_at (the day it was reported to CampusFind).
 *  - Open FOUND reports older than the retention period are "eligible"; closed reports may also be archived as housekeeping.
 *  - Returned items are never archived (they stay in the public returned-items history).
 */
final class ReportArchiveService
{
    public static function retentionDays()
    {
        return SettingsService::retentionDays();
    }

    /** Reports created at or before this UTC moment have passed the retention period. */
    public static function cutoffUtc()
    {
        return gmdate('Y-m-d H:i:s', time() - self::retentionDays() * 86400);
    }

    /** UTC date-time when a report created at $createdUtc becomes eligible. */
    public static function eligibleOn($createdUtc)
    {
        $ts = utc_ts($createdUtc);
        return $ts === null ? '' : gmdate('Y-m-d H:i:s', $ts + self::retentionDays() * 86400);
    }

    public static function eligibleCount()
    {
        return db_count("SELECT COUNT(*) FROM item_reports WHERE report_type = 'found' AND status = 'open' AND created_at <= ?", array(self::cutoffUtc()));
    }

    /** Open found items past the retention period, oldest first. */
    public static function eligible($page, $perPage)
    {
        $total = self::eligibleCount();
        $pg = paginate($total, $perPage, $page);
        $rows = db_all("SELECT r.*, c.name AS category_name, u.full_name AS reporter_name FROM item_reports r JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = r.user_id "
            . "WHERE r.report_type = 'found' AND r.status = 'open' AND r.created_at <= ? ORDER BY r.created_at ASC, r.id ASC LIMIT " . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'], array(self::cutoffUtc()));
        return array('rows' => $rows, 'pg' => $pg);
    }

    /** Ids of every currently eligible report (used by "archive all eligible"), capped for safety. */
    public static function eligibleIds($limit)
    {
        $ids = array();
        foreach (db_all("SELECT id FROM item_reports WHERE report_type = 'found' AND status = 'open' AND created_at <= ? ORDER BY created_at ASC, id ASC LIMIT " . (int) $limit, array(self::cutoffUtc())) as $r) {
            $ids[] = (int) $r['id'];
        }
        return $ids;
    }

    /** Open found items that become eligible within the next $days days. */
    public static function approaching($days)
    {
        $from = self::cutoffUtc();
        $to = gmdate('Y-m-d H:i:s', time() - self::retentionDays() * 86400 + (int) $days * 86400);
        return db_all("SELECT r.*, c.name AS category_name FROM item_reports r JOIN categories c ON c.id = r.category_id "
            . "WHERE r.report_type = 'found' AND r.status = 'open' AND r.created_at > ? AND r.created_at <= ? ORDER BY r.created_at ASC", array($from, $to));
    }

    /** array(allowed, reason) for one report row. */
    public static function check($report)
    {
        $status = $report['status'];
        if ($status === 'archived') {
            return array(false, 'This report is already archived.');
        }
        if ($status === 'returned') {
            return array(false, 'Returned items stay in the public returned-items history and cannot be archived.');
        }
        if ($status === 'claim_pending') {
            return array(false, 'This item has an active claim. Finish or reject the claim first.');
        }
        if ($status === 'closed') {
            return array(true, '');
        }
        if ($report['report_type'] !== 'found') {
            return array(false, 'Only found items are archived as unclaimed. Close this lost report first, then archive it.');
        }
        if ($report['created_at'] > self::cutoffUtc()) {
            return array(false, 'The ' . self::retentionDays() . '-day retention period has not passed yet (eligible after ' . fmt_dt(self::eligibleOn($report['created_at'])) . ').');
        }
        return array(true, '');
    }

    /** Archive one report. Records archived_at, archived_by, status history and notifies the reporter. */
    public static function archive($actor, $reportId, $notes = '')
    {
        if (!is_staff_user($actor)) {
            return array('ok' => false, 'errors' => array('Only staff can archive reports.'));
        }
        $notes = clean_text($notes);
        if (text_len($notes) > 500) {
            return array('ok' => false, 'errors' => array('Notes must be 500 characters or fewer.'));
        }
        try {
            db_transaction(function ($pdo) use ($actor, $reportId, $notes) {
                $report = ReportService::find($reportId);
                if (!$report) {
                    throw new BusinessRuleException('That report could not be found.');
                }
                $check = ReportArchiveService::check($report);
                if (!$check[0]) {
                    throw new BusinessRuleException($check[1]);
                }
                $now = now_utc();
                $stmt = $pdo->prepare("UPDATE item_reports SET status = 'archived', archived_at = ?, archived_by = ?, updated_at = ? WHERE id = ? AND status = ?");
                $stmt->execute(array($now, (int) $actor['id'], $now, (int) $report['id'], $report['status']));
                if ($stmt->rowCount() !== 1) {
                    throw new BusinessRuleException('This report was just changed by someone else. Reload the page and try again.');
                }
                $why = ($report['status'] === 'open')
                    ? 'Archived: unclaimed after the ' . ReportArchiveService::retentionDays() . '-day retention period'
                    : 'Archived: closed report moved out of the active lists';
                HistoryService::item($report['id'], $actor['id'], $report['status'], 'archived', $notes !== '' ? $why . '. ' . $notes : $why);
                if ((int) $report['user_id'] !== (int) $actor['id']) {
                    NotificationService::create((int) $report['user_id'], 'report_archived', 'Your report "' . $report['item_name'] . '" was archived.', 'report', (int) $report['id']);
                }
            });
        } catch (BusinessRuleException $e) {
            return array('ok' => false, 'errors' => array($e->getMessage()));
        }
        return array('ok' => true, 'errors' => array());
    }

    /** Archived reports with who archived them. Filters: q, category_id, date_from, date_to (Manila dates of archived_at). */
    public static function listArchived($f, $page, $perPage)
    {
        $where = array("r.status = 'archived'");
        $params = array();
        if (!empty($f['q'])) {
            $like = like_pattern($f['q']);
            $where[] = "(r.item_name LIKE ? ESCAPE '!' OR r.location LIKE ? ESCAPE '!')";
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($f['category_id'])) {
            $where[] = 'r.category_id = ?';
            $params[] = (int) $f['category_id'];
        }
        $b = local_date_bounds(isset($f['date_from']) ? $f['date_from'] : '', isset($f['date_to']) ? $f['date_to'] : '');
        if ($b[0] !== null) {
            $where[] = 'r.archived_at >= ?';
            $params[] = $b[0];
        }
        if ($b[1] !== null) {
            $where[] = 'r.archived_at < ?';
            $params[] = $b[1];
        }
        $from = 'FROM item_reports r JOIN categories c ON c.id = r.category_id LEFT JOIN users a ON a.id = r.archived_by WHERE ' . implode(' AND ', $where);
        $total = db_count('SELECT COUNT(*) ' . $from, $params);
        $pg = paginate($total, $perPage, $page);
        $rows = db_all('SELECT r.*, c.name AS category_name, a.full_name AS archived_by_name ' . $from
            . ' ORDER BY r.archived_at DESC, r.id DESC LIMIT ' . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'], $params);
        return array('rows' => $rows, 'pg' => $pg);
    }

    /** Archive several reports; each one is checked on its own. Returns array('done' => n, 'errors' => [...]). */
    public static function archiveMany($actor, $ids, $notes = '')
    {
        $done = 0;
        $errors = array();
        foreach ($ids as $id) {
            $r = self::archive($actor, (int) $id, $notes);
            if ($r['ok']) {
                $done++;
            } else {
                $errors[] = 'Report #' . (int) $id . ': ' . $r['errors'][0];
            }
        }
        return array('done' => $done, 'errors' => $errors);
    }
}
