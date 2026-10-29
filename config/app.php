<?php
declare(strict_types=1);
define('APP_NAME', 'CampusFind');
define('BASE_URL', '/CampusFind_Project'); // Use '' when serving this folder as the web root.
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('CAMPUSFIND_SESSION');
    session_start();
}
