<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$managePath = 'staff/manage-items.php';
require __DIR__ . '/../includes/views/report-manager.php';
