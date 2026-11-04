<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$archivedPath = 'admin/archived-reports.php';
$pageTitle = 'Archived reports';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Archived reports</h1><p>Reports moved out of the active lists after the retention period (or closed and tidied away). Their history and claims stay in the database. Returned items are not archived: they remain in the public returned-items history.</p></div></div>
<?php require __DIR__ . '/../includes/views/archived-table.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
