<?php
require __DIR__ . '/includes/bootstrap.php';

// Logout only accepts POST with a valid CSRF token, so another site cannot log users out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();
audit('logout', 'User logged out');
logout_user();
start_secure_session();
flash('success', 'You have been logged out.');
redirect('login.php');
