<?php
/**
 * Forgot Password Flow with Role Selection and 2-Step OTP Verification
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$step = (int)($_GET['step'] ?? 1);
$error = '';
$selectedRole = $_POST['role'] ?? 'students';
$identifier = $_POST['identifier'] ?? '';

// Step 3: Set New Password (Must be verified via OTP)
if ($step === 3) {
    if (!isset($_SESSION['reset_password_verified'])) {
        header('Location: ' . BASE_URL . '/forgot_password.php?step=1');
        exit;
    }

    $verifiedData = $_SESSION['reset_password_verified'];
    $userId = $verifiedData['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match. Please re-enter.';
        } else {
            // Generate a fresh unique salt for added post-reset security!
            $newSalt = generate_user_salt(16);
            $newHash = hash_password_salt_pepper($newPassword, $newSalt);

            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, salt = ?, otp_code = ?, otp_expires_at = ? WHERE id = ?");
            $stmt->execute([$newHash, $newSalt, null, null, $userId]);

            // Clear reset session
            unset($_SESSION['reset_password_verified']);

            // Get username for audit
            $uStmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
            $uStmt->execute([$userId]);
            $uRow = $uStmt->fetch();

            log_audit($uRow['username'], $uRow['role'], 'PASSWORD_RESET_SUCCESS', 'Password reset completed.');

            set_flash('success', 'Your password was successfully updated. Please sign in now.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

// Step 1: Request Password Reset & Select Role
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRole = trim($_POST['role'] ?? '');
    $identifier   = trim($_POST['identifier'] ?? '');

    if (empty($selectedRole) || empty($identifier)) {
        $error = 'Please select your role and enter your username or email.';
    } elseif (!array_key_exists($selectedRole, ALLOWED_ROLES)) {
        $error = 'Invalid user role selected.';
    } else {
        $user = find_user_by_login($identifier);

        if (!$user) {
            $error = 'No account found matching the provided username or email.';
        } elseif ($user['role'] !== $selectedRole) {
            $expectedRoleName = ALLOWED_ROLES[$user['role']] ?? $user['role'];
            $error = "Role mismatch: This account is registered as '{$expectedRoleName}', not '" . (ALLOWED_ROLES[$selectedRole] ?? $selectedRole) . "'.";
        } else {
            try {
                dispatch_otp($user['id'], $user['email'], $user['fullname']);
                $_SESSION['pending_2fa'] = [
                    'user_id'  => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'email'    => $user['email'],
                    'role'     => $user['role'],
                    'purpose'  => 'reset_password'
                ];

                log_audit($user['username'], $user['role'], 'PASSWORD_RESET_OTP_SENT', 'Password reset verification code emailed.');
                header('Location: ' . BASE_URL . '/verify_otp.php');
                exit;
            } catch (RuntimeException $exception) {
                error_log('Password reset OTP email delivery failed: ' . $exception->getMessage());
                $error = 'Unable to send the verification email. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Forgot Password - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-25 mb-2 text-primary">
                <i class="bi bi-key-fill fs-2 text-white"></i>
            </div>
            <h3 class="fw-bold mb-1">Reset Password</h3>
            <p class="text-light text-opacity-75 small mb-0">Role Verification & 2-Step OTP Authentication</p>
        </div>

        <div class="auth-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- STEP 1: Select Role and Username/Email -->
                <p class="text-muted small mb-3">
                    Enter your account username or registered email and select your user role. A 6-digit OTP code will be generated to verify your identity.
                </p>

                <form action="<?= BASE_URL ?>/forgot_password.php?step=1" method="POST">
                    <!-- Role Selection -->
                    <div class="mb-3">
                        <label for="role" class="form-label"><i class="bi bi-shield-check text-primary me-1"></i> Confirm Your Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="students" <?= ($selectedRole === 'students') ? 'selected' : '' ?>>Student (students)</option>
                            <option value="libarian" <?= ($selectedRole === 'libarian') ? 'selected' : '' ?>>Librarian (libarian)</option>
                            <option value="books" <?= ($selectedRole === 'books') ? 'selected' : '' ?>>Books Manager (books)</option>
                            <option value="admin" <?= ($selectedRole === 'admin') ? 'selected' : '' ?>>Administrator (admin)</option>
                        </select>
                    </div>

                    <!-- Username or Email -->
                    <div class="mb-3">
                        <label for="identifier" class="form-label"><i class="bi bi-person text-secondary me-1"></i> Username or Email</label>
                        <input type="text" class="form-control" id="identifier" name="identifier" 
                               placeholder="e.g. student1 or admin@apexlib.local" value="<?= htmlspecialchars($identifier) ?>" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-shield-lock"></i> Request Verification OTP
                    </button>
                </form>

            <?php elseif ($step === 3): ?>
                <!-- STEP 3: Enter New Password -->
                <div class="alert alert-success py-2 px-3 small d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    <div>2-Step OTP Identity Verified! Please enter your new password below.</div>
                </div>

                <form action="<?= BASE_URL ?>/forgot_password.php?step=3" method="POST">
                    <div class="mb-3">
                        <label for="new_password" class="form-label"><i class="bi bi-lock text-secondary me-1"></i> New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" 
                               placeholder="Minimum 6 characters" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label"><i class="bi bi-check2-circle text-secondary me-1"></i> Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Re-enter new password" required>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                        <i class="bi bi-arrow-repeat"></i> Update Password
                    </button>
                </form>
            <?php endif; ?>

            <hr class="my-4">

            <div class="text-center">
                <a href="<?= BASE_URL ?>/login.php" class="text-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Return to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
