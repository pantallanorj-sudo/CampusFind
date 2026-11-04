<?php
/** Append-only audit trail for item reports (status_history) and claims (claim_status_history). */
final class HistoryService
{
    public static function item($reportId, $userId, $old, $new, $notes = null)
    {
        db()->prepare('INSERT INTO status_history (item_report_id, changed_by, old_status, new_status, notes, changed_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(array((int) $reportId, $userId === null ? null : (int) $userId, $old, $new, $notes, now_utc()));
    }

    public static function claim($claimId, $userId, $old, $new, $notes = null)
    {
        db()->prepare('INSERT INTO claim_status_history (claim_id, changed_by, old_status, new_status, notes, changed_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(array((int) $claimId, $userId === null ? null : (int) $userId, $old, $new, $notes, now_utc()));
    }

    public static function forReport($reportId)
    {
        return db_all('SELECT h.*, u.full_name AS actor_name FROM status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.item_report_id = ? ORDER BY h.changed_at ASC, h.id ASC', array((int) $reportId));
    }

    public static function forClaim($claimId)
    {
        return db_all('SELECT h.*, u.full_name AS actor_name FROM claim_status_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.claim_id = ? ORDER BY h.changed_at ASC, h.id ASC', array((int) $claimId));
    }

    /**
     * Admin audit screen. $kind is 'item' or 'claim'.
     * Filters: q (item name), status (new status), actor (user id), subject_id, date_from, date_to (Manila dates).
     */
    public static function feed($kind, $f, $page, $perPage)
    {
        $isClaim = ($kind === 'claim');
        $where = array('1 = 1');
        $params = array();
        if (!empty($f['q'])) {
            $where[] = "r.item_name LIKE ? ESCAPE '!'";
            $params[] = like_pattern($f['q']);
        }
        $allowed = $isClaim ? CLAIM_STATUSES : REPORT_STATUSES;
        if (!empty($f['status']) && in_array($f['status'], $allowed, true)) {
            $where[] = 'h.new_status = ?';
            $params[] = $f['status'];
        }
        if (!empty($f['actor'])) {
            $where[] = 'h.changed_by = ?';
            $params[] = (int) $f['actor'];
        }
        if (!empty($f['subject_id'])) {
            $where[] = $isClaim ? 'h.claim_id = ?' : 'h.item_report_id = ?';
            $params[] = (int) $f['subject_id'];
        }
        $bounds = local_date_bounds(isset($f['date_from']) ? $f['date_from'] : '', isset($f['date_to']) ? $f['date_to'] : '');
        if ($bounds[0] !== null) {
            $where[] = 'h.changed_at >= ?';
            $params[] = $bounds[0];
        }
        if ($bounds[1] !== null) {
            $where[] = 'h.changed_at < ?';
            $params[] = $bounds[1];
        }
        if ($isClaim) {
            $from = 'FROM claim_status_history h JOIN claim_requests q ON q.id = h.claim_id JOIN item_reports r ON r.id = q.item_report_id LEFT JOIN users u ON u.id = h.changed_by ';
            $select = 'SELECT h.*, r.item_name, r.id AS report_id, h.claim_id AS subject_id, u.full_name AS actor_name, u.role AS actor_role ';
        } else {
            $from = 'FROM status_history h JOIN item_reports r ON r.id = h.item_report_id LEFT JOIN users u ON u.id = h.changed_by ';
            $select = 'SELECT h.*, r.item_name, r.id AS report_id, h.item_report_id AS subject_id, u.full_name AS actor_name, u.role AS actor_role ';
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $total = db_count('SELECT COUNT(*) ' . $from . $whereSql, $params);
        $pg = paginate($total, $perPage, $page);
        $rows = db_all($select . $from . $whereSql . ' ORDER BY h.changed_at DESC, h.id DESC LIMIT ' . (int) $pg['per_page'] . ' OFFSET ' . (int) $pg['offset'], $params);
        return array('rows' => $rows, 'pg' => $pg);
    }
}
