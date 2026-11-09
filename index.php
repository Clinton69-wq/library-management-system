<?php
/**
 * Root Entrypoint
 * Redirects to dashboard if authenticated, or login.php if guest
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $role = $_SESSION['user']['role'];
    header('Location: ' . get_dashboard_url_for_role($role));
    exit;
}

header('Location: ' . BASE_URL . '/login.php');
exit;

