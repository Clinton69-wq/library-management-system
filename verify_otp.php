<?php
/**
 * Professional Two-Step Verification (OTP) Screen
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure a pending 2FA session exists
if (!isset($_SESSION['pending_2fa'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$pending = $_SESSION['pending_2fa'];
$userId = $pending['user_id'];
$error = '';
$success = '';

// Handle Resend OTP Request
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    try {
        dispatch_otp($userId, $pending['email'], $pending['fullname']);
        log_audit($pending['username'], $pending['role'], '2FA_OTP_RESENT', 'Verification code emailed again.');
        $success = 'A new 6-digit verification code has been sent to your email.';
    } catch (RuntimeException $exception) {
        error_log('OTP resend failed: ' . $exception->getMessage());
        $error = 'Unable to send the verification email. Please try again later.';
    }
}

// Fetch active OTP from database to display in localhost demonstration toast
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();
$maskedEmail = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $userData['email'] ?? $pending['email']);
$showOtp = APP_ENV === 'development' || OTP_DEMO_MODE;
$activeOtp = $showOtp ? ($_SESSION['last_generated_otp']['code'] ?? '------') : '';

// Handle OTP Verification Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['full_otp_code'] ?? '');
    
    // If user filled individual boxes instead of hidden combined
    if (empty($code) && isset($_POST['digit']) && is_array($_POST['digit'])) {
        $code = implode('', $_POST['digit']);
    }

    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = 'Please enter a valid 6-digit numeric verification code.';
    } else {
        $res = verify_otp_code($userId, $code);

        if (!$res['success']) {
            $error = $res['message'];
            log_audit($pending['username'], $pending['role'], '2FA_FAILED', 'Incorrect verification code.');
        } else {
            // OTP Verified Successfully!
            if ($pending['purpose'] === 'reset_password') {
                $_SESSION['reset_password_verified'] = [
                    'user_id' => $userId,
                    'role'    => $pending['role']
                ];
                unset($_SESSION['pending_2fa']);
                header('Location: ' . BASE_URL . '/forgot_password.php?step=3');
                exit;
            } else {
                // Log the user into session
                $userStmt = $pdo->prepare("SELECT id, fullname, username, email, role, created_at FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();

                $_SESSION['user'] = $user;
                unset($_SESSION['pending_2fa']);

                log_audit($user['username'], $user['role'], 'LOGIN_SUCCESS_2FA', 'Completed 2-Step OTP Authentication');

                set_flash('success', "Welcome back, <strong>" . htmlspecialchars($user['fullname']) . "</strong>! Authenticated successfully.");
                header('Location: ' . get_dashboard_url_for_role($user['role']));
                exit;
            }
        }
    }
}

$pageTitle = 'Two-Step Verification - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-header">
            <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-25 mb-2 text-primary">
                <i class="bi bi-shield-check fs-2 text-white"></i>
            </div>
            <h3 class="fw-bold mb-1">Two-Step Verification</h3>
            <p class="text-light text-opacity-75 small mb-0">High-Security OTP Authentication</p>
        </div>

        <div class="auth-body text-center">
            <?php if ($showOtp): ?>
                <div class="alert alert-info py-2 px-3 small border border-info border-opacity-50 text-start mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                            <?= OTP_DEMO_MODE ? 'INSECURE OTP DEMO MODE' : 'Localhost OTP Simulation' ?>
                        </span>
                        <span class="badge bg-warning text-dark">Demo only</span>
                    </div>
                    <div class="text-muted" style="font-size:0.825rem;">
                        <?= OTP_DEMO_MODE
                            ? 'The OTP is shown on this page. Anyone who knows the password can sign in; this is not two-factor authentication.'
                            : 'Code dispatched to <strong>' . htmlspecialchars($maskedEmail) . '</strong>.<br>For testing on localhost, your active OTP is:' ?>
                        <br>
                        <span class="badge bg-dark font-monospace fs-6 px-2 py-1 ms-1 text-warning"><?= htmlspecialchars($activeOtp) ?></span>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-3">A verification code was sent to <?= htmlspecialchars($maskedEmail) ?>.</p>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-3 text-start" role="alert">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success py-2 px-3 small d-flex align-items-center gap-2 mb-3 text-start" role="alert">
                    <i class="bi bi-check-circle-fill text-success"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <p class="text-muted small mb-3">
                Please enter the 6-digit one-time passcode sent to authenticate your account 
                (<strong><?= htmlspecialchars($pending['username']) ?></strong> &bull; 
                <span class="role-pill role-<?= htmlspecialchars($pending['role']) ?>"><?= htmlspecialchars(ALLOWED_ROLES[$pending['role']] ?? $pending['role']) ?></span>).
            </p>

            <form action="<?= BASE_URL ?>/verify_otp.php" method="POST" id="otp_form">
                <!-- Hidden Full OTP Input -->
                <input type="hidden" name="full_otp_code" id="full_otp_code" value="">

                <!-- 6 Individual Segmented Digits with auto-advance and paste handler -->
                <div class="otp-container">
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-unlock"></i> Verify & Authenticate
                </button>
            </form>

            <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top small text-muted">
                <span>Didn't receive code?</span>
                <a href="<?= BASE_URL ?>/verify_otp.php?resend=1" class="text-primary fw-semibold text-decoration-none">
                    <i class="bi bi-arrow-repeat me-1"></i> Resend OTP
                </a>
            </div>

            <div class="mt-3">
                <a href="<?= BASE_URL ?>/login.php" class="text-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Cancel and Return to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
