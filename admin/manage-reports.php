<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$managePath = 'admin/manage-reports.php';
require __DIR__ . '/../includes/views/report-manager.php';
