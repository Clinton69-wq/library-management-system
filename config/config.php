<?php
/**
 * Application Configuration & Security Constants
 * Library Management System
 */

// Start session securely if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Optional server-only overrides for shared hosting credentials and SMTP settings.
$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    require_once $localConfig;
}

// Database Connection Parameters (XAMPP Default)
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'library_db');
}
if (!defined('DB_USER')) {
    define('DB_USER', getenv('DB_USER') ?: 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getenv('DB_PASS') ?: '');
}
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getenv('SMTP_PORT') ?: '587');
}
if (!defined('SMTP_SECURE')) {
    define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
}
if (!defined('SMTP_USERNAME')) {
    define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
}
if (!defined('SMTP_PASSWORD')) {
    define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
}
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'ApexLib Cloud Library System');
}
if (!defined('OTP_DEMO_MODE')) {
    define('OTP_DEMO_MODE', getenv('OTP_DEMO_MODE') === '1');
}

/**
 * SERVER-SIDE SECRET PEPPER
 * 
 * Cryptographic Architecture:
 * - Salt: Generated uniquely for EACH user and stored in database (viewable in UI).
 * - Pepper: Secret global key stored here on the server file system (NEVER in the DB).
 * 
 * Hashing Chain:
 *   Input Password -> Concatenate User Salt -> HMAC with System Pepper (SHA256) -> BCrypt Hash
 * 
 * Even if an attacker dumps the entire SQL database (including salts and hashes),
 * they CANNOT perform offline dictionary or rainbow table attacks without this Pepper key!
 */
define('SYSTEM_PEPPER', 'Sec!Lib2026#GlobalP@pperKey_v9x!84Kx#90_CloudflareVault');

// Application Details
define('APP_NAME', 'ApexLib Cloud Library System');
define('APP_VERSION', '2.5.0-Enterprise');
$defaultAppEnv = in_array(strtolower($_SERVER['HTTP_HOST'] ?? ''), ['localhost', '127.0.0.1'], true)
    ? 'development'
    : 'production';
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: $defaultAppEnv);
}
if (!defined('BASE_URL')) {
    $baseUrl = getenv('APP_BASE_URL');
    if ($baseUrl === false) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $runningFromCli = PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST']);
        $baseUrl = $runningFromCli || $scriptName === ''
            ? '/libary'
            : str_replace('\\', '/', dirname($scriptName));
        if ($baseUrl === '.') {
            $baseUrl = '';
        }
    }
    define('BASE_URL', rtrim($baseUrl, '/'));
}

// Cloudflare Storage Defaults for Backup Records
define('CF_ACCOUNT_ID', 'cf_acc_98a72e41c09b882');
define('CF_R2_BUCKET', 'apex-library-r2-vault');
define('CF_REGION', 'auto');
define('CF_ZONE_ID', 'cf_zone_3049284028304928');
define('CF_ENDPOINT_HOST', 'https://r2.cloudflarestorage.com/apex-library-r2-vault');

// Backups Directory on Local Storage
define('BACKUP_DIR', __DIR__ . '/../backups');

// Allowed User Roles per System Specifications
const ALLOWED_ROLES = [
    'admin'    => 'Administrator',
    'students' => 'Student',
    'libarian' => 'Librarian',
    'books'    => 'Books Manager'
];
