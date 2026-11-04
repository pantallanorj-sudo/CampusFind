<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_staff();
$queuePath = 'staff/manage-claims.php';
require __DIR__ . '/../includes/views/claim-queue.php';
