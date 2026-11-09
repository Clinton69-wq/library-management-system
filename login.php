<?php
/**
 * Sign In Page with Role Selection and 2-Step OTP Authentication
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header('Location: ' . get_dashboard_url_for_role($_SESSION['user']['role']));
    exit;
}

$error = '';
$selectedRole = $_POST['role'] ?? 'students';
$identifier = $_POST['identifier'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRole = trim($_POST['role'] ?? '');
    $identifier   = trim($_POST['identifier'] ?? '');
    $password     = $_POST['password'] ?? '';

    if (empty($selectedRole) || empty($identifier) || empty($password)) {
        $error = 'Please fill in all fields and select your role.';
    } elseif (!array_key_exists($selectedRole, ALLOWED_ROLES)) {
        $error = 'Invalid role selected. Please choose a valid user role.';
    } else {
        $user = find_user_by_login($identifier);

        if (!$user) {
            $error = 'Invalid credentials or user account does not exist.';
            log_audit($identifier, $selectedRole, 'LOGIN_FAILED', 'Account not found');
        } elseif ($user['role'] !== $selectedRole) {
            $expectedRoleName = ALLOWED_ROLES[$user['role']] ?? $user['role'];
            $error = "Role mismatch: This account has '{$expectedRoleName}' privileges, but you selected '" . (ALLOWED_ROLES[$selectedRole] ?? $selectedRole) . "'.";
            log_audit($identifier, $selectedRole, 'LOGIN_ROLE_MISMATCH', "User attempted to login with role '{$selectedRole}' instead of '{$user['role']}'");
        } else {
            // Verify password using Salt & Pepper
            $isValidPassword = verify_password_salt_pepper($password, $user['salt'], $user['password_hash']);

            if (!$isValidPassword) {
                $error = 'Incorrect password. Please verify your credentials.';
                log_audit($identifier, $selectedRole, 'LOGIN_BAD_PASSWORD', 'Invalid password supplied');
            } else {
                // Two-Step Verification (OTP)
                try {
                    dispatch_otp($user['id'], $user['email'], $user['fullname']);
                    $_SESSION['pending_2fa'] = [
                        'user_id'  => $user['id'],
                        'username' => $user['username'],
                        'fullname' => $user['fullname'],
                        'email'    => $user['email'],
                        'role'     => $user['role'],
                        'purpose'  => 'login'
                    ];

                    log_audit($user['username'], $user['role'], '2FA_OTP_GENERATED', 'Verification code emailed.');
                    header('Location: ' . BASE_URL . '/verify_otp.php');
                    exit;
                } catch (RuntimeException $exception) {
                    error_log('OTP email delivery failed: ' . $exception->getMessage());
                    $error = 'Unable to send the verification email. Please try again later.';
                }
            }
        }
    }
}

$pageTitle = 'Sign In - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <!-- Card Header -->
        <div class="auth-header">
            <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-25 mb-2 text-primary">
                <i class="bi bi-shield-lock-fill fs-2 text-white"></i>
            </div>
            <h3 class="fw-bold mb-1">ApexLib Portal</h3>
            <p class="text-light text-opacity-75 small mb-0">Multi-Role Secured Library Management System</p>
        </div>

        <div class="auth-body">
            <div class="alert alert-light border py-2 px-3 small d-flex align-items-center justify-content-between mb-3 text-secondary">
                <span><i class="bi bi-shield-lock text-primary me-1"></i> <strong>Two-step verification</strong> enabled</span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">Secure sign-in</span>
            </div>

            <!-- Error Banner -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-3 small" role="alert">
                    <i class="bi bi-exclamation-octagon-fill text-danger fs-6"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="<?= BASE_URL ?>/login.php" method="POST" autocomplete="off">
                <!-- Role Selection -->
                <div class="mb-3">
                    <label for="role_select" class="form-label d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-person-badge text-primary me-1"></i> Select Your Role</span>
                        <span class="badge bg-primary bg-opacity-10 text-primary">4 User Roles</span>
                    </label>
                    <select class="form-select" id="role_select" name="role" required>
                        <option value="" disabled <?= empty($selectedRole) ? 'selected' : '' ?>>-- Choose Account Role --</option>
                        <option value="admin" <?= ($selectedRole === 'admin') ? 'selected' : '' ?>>Administrator (admin)</option>
                        <option value="students" <?= ($selectedRole === 'students') ? 'selected' : '' ?>>Student (students)</option>
                        <option value="libarian" <?= ($selectedRole === 'libarian') ? 'selected' : '' ?>>Librarian (libarian)</option>
                        <option value="books" <?= ($selectedRole === 'books') ? 'selected' : '' ?>>Books Manager (books)</option>
                    </select>
                </div>

                <!-- Username or Email -->
                <div class="mb-3">
                    <label for="login_identifier" class="form-label">
                        <i class="bi bi-person text-secondary me-1"></i> Username or Email
                    </label>
                    <input type="text" class="form-control" id="login_identifier" name="identifier" 
                           placeholder="Enter your username or email" value="<?= htmlspecialchars($identifier) ?>" required>
                </div>

                <!-- Password -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label for="login_password" class="form-label mb-0">
                            <i class="bi bi-key text-secondary me-1"></i> Password
                        </label>
                        <a href="<?= BASE_URL ?>/forgot_password.php" class="text-primary small text-decoration-none fw-semibold">
                            Forgot Password?
                        </a>
                    </div>
                    <input type="password" class="form-control" id="login_password" name="password" 
                           placeholder="Enter your account password" required>
                </div>

                <!-- Sign In Button -->
                <button type="submit" class="btn btn-primary w-100 py-2 mt-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-box-arrow-in-right"></i> Proceed to 2-Step OTP Verification
                </button>
            </form>

            <hr class="my-4">

            <!-- Create Account Callout -->
            <div class="text-center">
                <span class="text-muted small">Don't have an account yet?</span>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline-secondary btn-sm ms-2 fw-semibold">
                    <i class="bi bi-person-plus me-1"></i> Create Account
                </a>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
