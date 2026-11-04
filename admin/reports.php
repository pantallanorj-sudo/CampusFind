<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$reportsPath = 'admin/reports.php';
require __DIR__ . '/../includes/views/reports-view.php';
