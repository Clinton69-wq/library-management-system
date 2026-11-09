<?php
/**
 * Restore Points Controller
 * Configuration-only restore handler
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_role('admin');

// Redirect to admin restore points tab
header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=restore_points');
exit;

