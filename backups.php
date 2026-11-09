<?php
/**
 * Backups Controller & Secure SQL Downloader
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Strict Role Guard: Only Admin can access backups
require_role('admin');

// 1. Download Backup File
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filePath = BACKUP_DIR . '/' . $filename;

    if (!file_exists($filePath)) {
        set_flash('error', "Backup file '{$filename}' not found on server.");
        header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=backups');
        exit;
    }

    // Force secure download headers
    header('Content-Description: File Transfer');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// 2. Trigger Backup from GET/POST redirect
if (isset($_GET['create']) && $_GET['create'] == '1') {
    $u = current_user();
    $res = create_system_backup($u['username']);
    if ($res['success']) {
        set_flash('success', "Database backup created and registered with Cloudflare R2 Vault! [Ray ID: {$res['cf_ray_id']}]");
    } else {
        set_flash('error', 'Failed to generate backup.');
    }
}

header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=backups');
exit;

