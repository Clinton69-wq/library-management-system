<?php
/**
 * Logout Handler
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user'])) {
    $u = $_SESSION['user'];
    log_audit($u['username'], $u['role'], 'USER_LOGOUT', 'User signed out gracefully');
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

header('Location: ' . BASE_URL . '/login.php');
exit;

