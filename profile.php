<?php
/**
 * User Profile
 * Account credentials are used only by the server and are never displayed.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Require login
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$currentUser = current_user();
$pdo = getDBConnection();

// Fetch the account record required for password verification and profile details.
$stmt = $pdo->prepare("SELECT id, fullname, username, email, role, salt, password_hash, created_at FROM users WHERE id = ?");
$stmt->execute([$currentUser['id']]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Handle password changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (!verify_password_salt_pepper($currentPassword, $user['salt'], $user['password_hash'])) {
        $error = 'Current password entered is incorrect.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password confirmation does not match.';
    } else {
        // Generate a new Salt upon password change
        $newSalt = generate_user_salt(16);
        $newHash = hash_password_salt_pepper($newPassword, $newSalt);

        $upd = $pdo->prepare("UPDATE users SET password_hash = ?, salt = ? WHERE id = ?");
        $upd->execute([$newHash, $newSalt, $user['id']]);

        log_audit($user['username'], $user['role'], 'PASSWORD_CHANGED', 'User updated password.');

        // Refresh user record
        $stmt->execute([$currentUser['id']]);
        $user = $stmt->fetch();

        $success = 'Password successfully changed.';
    }
}

$pageTitle = 'My Profile - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="row">
        <!-- User Info Column -->
        <div class="col-lg-4 mb-4">
            <div class="card-custom h-100">
                <div class="card-custom-header">
                    <h5 class="fw-bold mb-0"><i class="bi bi-person-circle text-primary me-2"></i>Account Overview</h5>
                    <span class="role-pill role-<?= htmlspecialchars($user['role']) ?>">
                        <?= htmlspecialchars(ALLOWED_ROLES[$user['role']] ?? $user['role']) ?>
                    </span>
                </div>
                <div class="p-4 text-center border-bottom bg-light">
                    <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-10 text-primary mb-3">
                        <i class="bi bi-person fs-1"></i>
                    </div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($user['fullname']) ?></h4>
                    <p class="text-muted small mb-0">@<?= htmlspecialchars($user['username']) ?></p>
                </div>
                <div class="p-4">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">User ID:</span>
                            <span class="fw-semibold">#<?= htmlspecialchars($user['id']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Email:</span>
                            <span class="fw-semibold"><?= htmlspecialchars($user['email']) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">System Role:</span>
                            <span class="fw-semibold text-primary"><?= htmlspecialchars(ALLOWED_ROLES[$user['role']] ?? $user['role']) ?> (<?= htmlspecialchars($user['role']) ?>)</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">Registered On:</span>
                            <span class="fw-semibold"><?= date('M d, Y H:i', strtotime($user['created_at'])) ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2">
                            <span class="text-muted">2-Factor OTP:</span>
                            <span class="badge bg-success">Active & Verified</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Password Security Column -->
        <div class="col-lg-8 mb-4">
            <div class="card-custom mb-4 border-primary">
                <div class="card-custom-header bg-primary bg-opacity-10">
                    <h5 class="fw-bold mb-0 text-primary">
                        <i class="bi bi-shield-lock-fill me-2"></i>Password Security
                    </h5>
                    <span class="badge bg-success">Protected</span>
                </div>
                <div class="p-4">
                    <div class="security-callout mb-4">
                        <h6 class="fw-bold text-dark mb-2">Your account credentials are kept private.</h6>
                        <p class="small text-muted mb-0">
                            Password security details are not displayed on this page. You can update your password below.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="card-custom">
                <div class="card-custom-header">
                    <h5 class="fw-bold mb-0"><i class="bi bi-lock me-2"></i>Change Password</h5>
                </div>
                <div class="p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 px-3 small mb-3"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success py-2 px-3 small mb-3"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <form action="<?= BASE_URL ?>/profile.php" method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required minlength="6">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required minlength="6">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <i class="bi bi-arrow-repeat"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
