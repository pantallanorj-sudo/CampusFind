<?php
/** Staff reports and CSV export. Exports never include emails, password data, claim details or evidence. */
final class ExportService
{
    const ROW_LIMIT = 5000;

    public static function types()
    {
        return array('lost' => 'Lost items', 'found' => 'Found items', 'claims' => 'Claims by status',
            'returned' => 'Returned items', 'unclaimed' => 'Unclaimed items', 'archived' => 'Archived reports');
    }

    public static function dateLabel($type)
    {
        $labels = array('lost' => 'Reported between', 'found' => 'Reported between', 'claims' => 'Submitted between',
            'returned' => 'Returned between', 'unclaimed' => 'Reported between', 'archived' => 'Archived between');
        return isset($labels[$type]) ? $labels[$type] : 'Between';
    }

    /** Status filter options for a report type (value => label), or null when the report has no status filter. */
    public static function statusOptions($type)
    {
        if ($type === 'lost' || $type === 'found') {
            return status_choices(REPORT_STATUSES);
        }
        if ($type === 'claims') {
            return status_choices(CLAIM_STATUSES);
        }
        if ($type === 'unclaimed') {
            return array('eligible' => 'Eligible for archiving', 'within' => 'Within retention');
        }
        return null;
    }

    public static function summary()
    {
        $claims = array();
        foreach (db_all('SELECT status, COUNT(*) AS n FROM claim_requests GROUP BY status') as $r) {
            $claims[$r['status']] = (int) $r['n'];
        }
        foreach (CLAIM_STATUSES as $s) {
            if (!isset($claims[$s])) {
                $claims[$s] = 0;
            }
        }
        return array(
            'lost_total' => db_count("SELECT COUNT(*) FROM item_reports WHERE report_type = 'lost'"),
            'lost_open' => db_count("SELECT COUNT(*) FROM item_reports WHERE report_type = 'lost' AND status IN ('open','claim_pending')"),
            'found_total' => db_count("SELECT COUNT(*) FROM item_reports WHERE report_type = 'found'"),
            'found_open' => db_count("SELECT COUNT(*) FROM item_reports WHERE report_type = 'found' AND status IN ('open','claim_pending')"),
            'claims' => $claims,
            'claims_total' => array_sum($claims),
            'returned' => HandoverService::returnedCount(),
            'unclaimed_eligible' => ReportArchiveService::eligibleCount(),
            'closed' => db_count("SELECT COUNT(*) FROM item_reports WHERE status = 'closed'"),
            'archived' => db_count("SELECT COUNT(*) FROM item_reports WHERE status = 'archived'"),
        );
    }

    private static function dateClauses($column, $f)
    {
        $b = local_date_bounds(isset($f['date_from']) ? $f['date_from'] : '', isset($f['date_to']) ? $f['date_to'] : '');
        $where = array();
        $params = array();
        if ($b[0] !== null) {
            $where[] = $column . ' >= ?';
            $params[] = $b[0];
        }
        if ($b[1] !== null) {
            $where[] = $column . ' < ?';
            $params[] = $b[1];
        }
        return array($where, $params);
    }

    private static function stamp($utc)
    {
        $t = utc_ts($utc);
        return $t === null ? '' : date('Y-m-d H:i', $t);
    }

    /**
     * Returns array('title', 'headers', 'rows', 'total') for a report type.
     * Filters: category_id, status, date_from, date_to (Manila dates).
     */
    public static function dataset($type, $f)
    {
        $types = self::types();
        if (!isset($types[$type])) {
            throw new InvalidArgumentException('Unknown report type.');
        }
        $where = array();
        $params = array();
        $category = !empty($f['category_id']) ? (int) $f['category_id'] : 0;
        $status = isset($f['status']) ? $f['status'] : '';
        $limit = ' LIMIT ' . self::ROW_LIMIT;
        $out = array();

        if ($type === 'lost' || $type === 'found') {
            $where[] = 'r.report_type = ?';
            $params[] = $type;
            if ($category > 0) {
                $where[] = 'r.category_id = ?';
                $params[] = $category;
            }
            if (in_array($status, REPORT_STATUSES, true)) {
                $where[] = 'r.status = ?';
                $params[] = $status;
            }
            $d = self::dateClauses('r.created_at', $f);
            $where = array_merge($where, $d[0]);
            $params = array_merge($params, $d[1]);
            $rows = db_all('SELECT r.id, r.item_name, c.name AS category_name, r.description, r.location, r.date_occurred, r.status, r.created_at '
                . 'FROM item_reports r JOIN categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC, r.id DESC' . $limit, $params);
            foreach ($rows as $r) {
                $out[] = array($r['id'], $type === 'lost' ? 'Lost' : 'Found', $r['item_name'], $r['category_name'], $r['description'], $r['location'],
                    $r['date_occurred'], status_label($r['status']), self::stamp($r['created_at']));
            }
            $headers = array('Report ID', 'Type', 'Item name', 'Category', 'Description', 'Location', 'Date ' . $type, 'Status', 'Reported on (Manila time)');
        } elseif ($type === 'claims') {
            $where[] = '1 = 1';
            if ($category > 0) {
                $where[] = 'r.category_id = ?';
                $params[] = $category;
            }
            if (in_array($status, CLAIM_STATUSES, true)) {
                $where[] = 'q.status = ?';
                $params[] = $status;
            }
            $d = self::dateClauses('q.created_at', $f);
            $where = array_merge($where, $d[0]);
            $params = array_merge($params, $d[1]);
            $rows = db_all('SELECT q.id, q.item_report_id, r.item_name, c.name AS category_name, q.status, q.created_at, q.reviewed_at '
                . 'FROM claim_requests q JOIN item_reports r ON r.id = q.item_report_id JOIN categories c ON c.id = r.category_id '
                . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY q.created_at DESC, q.id DESC' . $limit, $params);
            foreach ($rows as $r) {
                $out[] = array($r['id'], $r['item_report_id'], $r['item_name'], $r['category_name'], status_label($r['status']), self::stamp($r['created_at']), self::stamp($r['reviewed_at']));
            }
            $headers = array('Claim ID', 'Report ID', 'Item name', 'Category', 'Claim status', 'Submitted on (Manila time)', 'Last reviewed on (Manila time)');
        } elseif ($type === 'returned') {
            $where[] = "q.status = 'returned'";
            $where[] = "r.status = 'returned'";
            if ($category > 0) {
                $where[] = 'r.category_id = ?';
                $params[] = $category;
            }
            $d = self::dateClauses('h.handed_over_at', $f);
            $where = array_merge($where, $d[0]);
            $params = array_merge($params, $d[1]);
            $rows = db_all('SELECT r.id, r.item_name, c.name AS category_name, r.date_occurred, r.created_at, q.id AS claim_id, h.handed_over_at '
                . 'FROM handover_records h JOIN claim_requests q ON q.id = h.claim_id JOIN item_reports r ON r.id = q.item_report_id JOIN categories c ON c.id = r.category_id '
                . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY h.handed_over_at DESC, h.id DESC' . $limit, $params);
            foreach ($rows as $r) {
                $days = (int) floor(((int) utc_ts($r['handed_over_at']) - (int) utc_ts($r['created_at'])) / 86400);
                $out[] = array($r['id'], $r['item_name'], $r['category_name'], $r['date_occurred'], $r['claim_id'], self::stamp($r['handed_over_at']), max(0, $days));
            }
            $headers = array('Report ID', 'Item name', 'Category', 'Date found', 'Claim ID', 'Returned on (Manila time)', 'Days from report to return');
        } elseif ($type === 'unclaimed') {
            $cut = ReportArchiveService::cutoffUtc();
            $where[] = "r.report_type = 'found'";
            $where[] = "r.status = 'open'";
            if ($category > 0) {
                $where[] = 'r.category_id = ?';
                $params[] = $category;
            }
            if ($status === 'eligible') {
                $where[] = 'r.created_at <= ?';
                $params[] = $cut;
            } elseif ($status === 'within') {
                $where[] = 'r.created_at > ?';
                $params[] = $cut;
            }
            $d = self::dateClauses('r.created_at', $f);
            $where = array_merge($where, $d[0]);
            $params = array_merge($params, $d[1]);
            $rows = db_all('SELECT r.id, r.item_name, c.name AS category_name, r.location, r.date_occurred, r.created_at '
                . 'FROM item_reports r JOIN categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at ASC, r.id ASC' . $limit, $params);
            foreach ($rows as $r) {
                $out[] = array($r['id'], $r['item_name'], $r['category_name'], $r['location'], $r['date_occurred'], self::stamp($r['created_at']),
                    days_since($r['created_at']), ReportArchiveService::retentionDays(), ($r['created_at'] <= $cut) ? 'Eligible for archiving' : 'Within retention');
            }
            $headers = array('Report ID', 'Item name', 'Category', 'Location', 'Date found', 'Reported on (Manila time)', 'Age (days)', 'Retention (days)', 'Retention status');
        } else {
            $where[] = "r.status = 'archived'";
            if ($category > 0) {
                $where[] = 'r.category_id = ?';
                $params[] = $category;
            }
            $d = self::dateClauses('r.archived_at', $f);
            $where = array_merge($where, $d[0]);
            $params = array_merge($params, $d[1]);
            $rows = db_all('SELECT r.id, r.report_type, r.item_name, c.name AS category_name, r.location, r.date_occurred, r.created_at, r.archived_at '
                . 'FROM item_reports r JOIN categories c ON c.id = r.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY r.archived_at DESC, r.id DESC' . $limit, $params);
            foreach ($rows as $r) {
                $out[] = array($r['id'], ucfirst($r['report_type']), $r['item_name'], $r['category_name'], $r['location'], $r['date_occurred'],
                    self::stamp($r['created_at']), self::stamp($r['archived_at']));
            }
            $headers = array('Report ID', 'Type', 'Item name', 'Category', 'Location', 'Date lost/found', 'Reported on (Manila time)', 'Archived on (Manila time)');
        }
        return array('title' => $types[$type], 'headers' => $headers, 'rows' => $out, 'total' => count($out));
    }

    /** One CSV cell: RFC 4180 quoting plus neutralising of spreadsheet formulas (=, +, -, @, tab, CR). */
    public static function csvCell($value)
    {
        $s = (string) $value;
        $first = ltrim($s);
        if ($first !== '' && strpos("=+-@\t\r", $first[0]) !== false) {
            $s = "'" . $s;
        }
        if (preg_match('/[",\r\n]/', $s) === 1) {
            $s = '"' . str_replace('"', '""', $s) . '"';
        }
        return $s;
    }

    public static function csvLine($cells)
    {
        $parts = array();
        foreach ($cells as $c) {
            $parts[] = self::csvCell($c);
        }
        return implode(',', $parts);
    }

    /** UTF-8 CSV with a BOM so Excel shows accents correctly; CRLF line endings. */
    public static function toCsv($headers, $rows)
    {
        $lines = array(self::csvLine($headers));
        foreach ($rows as $r) {
            $lines[] = self::csvLine($r);
        }
        return "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
    }

    public static function filename($type)
    {
        return 'campusfind-' . $type . '-' . date('Ymd-His') . '.csv';
    }
}
