<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_admin();
$queuePath = 'admin/manage-claims.php';
require __DIR__ . '/../includes/views/claim-queue.php';
