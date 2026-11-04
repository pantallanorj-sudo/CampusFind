<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();
require __DIR__ . '/../includes/views/notifications-list.php';
