<?php
/** CSV download (read-only, so GET is fine). Staff and administrators only. */
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$types = ExportService::types();
$type = get_enum('type', array_keys($types), '');
if ($type === '') {
    abort_page(400, 'Unknown report', 'Choose a report type from the Reports page.');
}
$statusOptions = ExportService::statusOptions($type);
$f = array(
    'category_id' => get_int('category_id'),
    'status' => ($statusOptions !== null) ? get_enum('status', array_keys($statusOptions), '') : '',
    'date_from' => get_date('date_from'),
    'date_to' => get_date('date_to'),
);
$data = ExportService::dataset($type, $f);
$reportsPath = ($me['role'] === 'admin') ? 'admin/reports.php' : 'staff/reports.php';
if ($data['total'] === 0) {
    flash('warning', 'No records match those filters, so no file was created.');
    redirect($reportsPath . '?' . build_query(array('type' => $type, 'category_id' => $f['category_id'] > 0 ? $f['category_id'] : '', 'status' => $f['status'], 'date_from' => $f['date_from'], 'date_to' => $f['date_to'])));
}
$csv = ExportService::toCsv($data['headers'], $data['rows']);
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . ExportService::filename($type) . '"');
header('Content-Length: ' . strlen($csv));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $csv;
exit;
