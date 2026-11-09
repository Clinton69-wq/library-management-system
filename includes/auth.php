<?php
/**
 * Authentication & Cryptographic Security Module
 * Implements Salt & Pepper Architecture, 2-Factor OTP, and Role Access Control
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Generate a unique cryptographic Salt for a new user
 * 16 bytes = 32 hexadecimal characters of cryptographically secure pseudo-random bytes
 */
function generate_user_salt($bytes = 16) {
    return bin2hex(random_bytes($bytes));
}

/**
 * Salt + Pepper Hashing Algorithm
 * 
 * 1. Takes the user plaintext password and their individual unique Salt.
 * 2. Uses the server-side SYSTEM_PEPPER key to compute an HMAC-SHA256 digest.
 * 3. Applies Bcrypt password_hash with adaptive work factor on the digest.
 * 
 * @param string $password User input password
 * @param string $salt Unique hex salt stored in database for this user
 * @return string Secure Bcrypt hash
 */
function hash_password_salt_pepper($password, $salt) {
    $salted = $password . $salt;
    $peppered = hash_hmac('sha256', $salted, SYSTEM_PEPPER);
    return password_hash($peppered, PASSWORD_BCRYPT, ['cost' => 11]);
}

/**
 * Verify a plaintext password against stored hash using Salt & Pepper
 */
function verify_password_salt_pepper($password, $salt, $hash) {
    $salted = $password . $salt;
    $peppered = hash_hmac('sha256', $salted, SYSTEM_PEPPER);
    return password_verify($peppered, $hash);
}

/**
 * Generate a 6-digit OTP for Two-Step Verification
 */
function generate_otp($userId) {
    $pdo = getDBConnection();
    // 6-digit random code
    $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    
    // Read expiry minutes from system configuration or fallback to 10
    $expiryMinutes = 10;
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM system_configurations WHERE config_key = 'otp_expiry_minutes'");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && is_numeric($row['config_value'])) {
            $expiryMinutes = (int)$row['config_value'];
        }
    } catch (Exception $e) {}

    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"));

    $stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
    $stmt->execute([$otp, $expiresAt, $userId]);

    // Store in session for quick access / simulation banner
    $_SESSION['last_generated_otp'] = [
        'user_id' => $userId,
        'code'    => $otp,
        'expires' => $expiresAt
    ];

    return $otp;
}

/**
 * Generate a verification code, deliver it, and invalidate it if delivery fails.
 */
function dispatch_otp($userId, $email, $fullname) {
    $otp = generate_otp($userId);
    if (APP_ENV === 'development' || OTP_DEMO_MODE) {
        return;
    }

    try {
        send_otp_email($email, $fullname, $otp);
    } catch (RuntimeException $exception) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
        $stmt->execute([null, null, $userId]);
        unset($_SESSION['last_generated_otp']);
        throw $exception;
    }
}

/**
 * Verify Two-Step OTP Code
 */
function verify_otp_code($userId, $enteredOtp) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT otp_code, otp_expires_at FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || empty($user['otp_code'])) {
        return ['success' => false, 'message' => 'No active verification code found. Please request a new one.'];
    }

    if (strtotime($user['otp_expires_at']) < time()) {
        return ['success' => false, 'message' => 'Verification code has expired. Please request a new code.'];
    }

    if (trim($user['otp_code']) !== trim($enteredOtp)) {
        return ['success' => false, 'message' => 'Incorrect verification code. Please check and try again.'];
    }

    // Clear OTP after successful verification
    $clear = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
    $clear->execute([null, null, $userId]);
    unset($_SESSION['last_generated_otp']);

    return ['success' => true];
}

/**
 * Find user by username or email
 */
function find_user_by_login($identifier) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT id, fullname, username, email, role, password_hash, salt
        FROM users
        WHERE username = ? OR email = ?
        LIMIT 1
    ");
    $stmt->execute([$identifier, $identifier]);
    return $stmt->fetch();
}

/**
 * Register a new user with Salt & Pepper
 */
function register_user($fullname, $username, $email, $role, $password) {
    $pdo = getDBConnection();

    // Check if role is allowed
    if ($role !== 'students') {
        return ['success' => false, 'message' => 'Public registration is limited to student accounts.'];
    }

    // Check username uniqueness
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Username is already taken.'];
    }

    // Check email uniqueness
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Email address is already registered.'];
    }

    // 1. Generate unique random cryptographic salt
    $salt = generate_user_salt(16);

    // 2. Hash using password + salt + system pepper
    $passwordHash = hash_password_salt_pepper($password, $salt);

    $insert = $pdo->prepare("INSERT INTO users (fullname, username, email, role, password_hash, salt, two_factor_enabled) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $insert->execute([$fullname, $username, $email, $role, $passwordHash, $salt]);
    $newUserId = $pdo->lastInsertId();

    // Log the audit
    log_audit($username, $role, 'USER_REGISTERED', "Created new {$role} account.");

    return [
        'success' => true,
        'user_id' => $newUserId
    ];
}

/**
 * Check if current session is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get currently authenticated user data
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Restrict page access by role
 */
function require_role($allowedRoles) {
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }

    if (!is_logged_in()) {
        $_SESSION['flash_error'] = 'Please sign in to access this page.';
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    $userRole = $_SESSION['user']['role'];
    if (!in_array($userRole, $allowedRoles)) {
        // Redirect to their own dashboard
        header('Location: ' . get_dashboard_url_for_role($userRole));
        exit;
    }
}

/**
 * Resolve Dashboard URL based on user role
 */
function get_dashboard_url_for_role($role) {
    switch ($role) {
        case 'admin':
            return BASE_URL . '/dashboard_admin.php';
        case 'students':
            return BASE_URL . '/dashboard_student.php';
        case 'libarian':
            return BASE_URL . '/dashboard_librarian.php';
        case 'books':
            return BASE_URL . '/dashboard_books.php';
        default:
            return BASE_URL . '/login.php';
    }
}

/**
 * Write to audit log
 */
function log_audit($username, $role, $action, $details = '') {
    try {
        $pdo = getDBConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (username, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $role, $action, $details, $ip]);
    } catch (Exception $e) {
        // Silently fail if audit logging encounters an issue
    }
}
