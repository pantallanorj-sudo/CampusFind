<?php
require_once __DIR__ . '/includes/auth.php';
if (!is_post()) {
    // Logging out is a state change, so it is POST + CSRF only. A stray GET just goes home.
    $me = current_user();
    redirect($me ? home_path($me['role']) : 'index.php');
}
verify_csrf();
logout_user();
flash('success', 'You have been logged out.');
redirect('login.php');
