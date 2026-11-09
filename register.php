<?php
/**
 * Create Account / Registration Page
 * Public registration is limited to student accounts.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    header('Location: ' . get_dashboard_url_for_role($_SESSION['user']['role']));
    exit;
}

$error = '';
$fullname = $_POST['fullname'] ?? '';
$username = $_POST['username'] ?? '';
$email    = $_POST['email'] ?? '';
$role     = 'students';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters in length.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match. Please re-enter carefully.';
    } else {
        $result = register_user($fullname, $username, $email, $role, $password);

        if (!$result['success']) {
            $error = $result['message'];
        } else {
            set_flash('success', "Account for <strong>" . htmlspecialchars($username) . "</strong> created successfully with role <strong>" . htmlspecialchars(ALLOWED_ROLES[$role] ?? $role) . "</strong>. You may now sign in.");
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

$pageTitle = 'Create Account - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-wrapper">
    <div class="auth-card auth-card-wide">
        <div class="auth-header">
            <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-25 mb-2 text-primary">
                <i class="bi bi-person-plus-fill fs-2 text-white"></i>
            </div>
            <h3 class="fw-bold mb-1">Create an Account</h3>
            <p class="text-light text-opacity-75 small mb-0">Create your ApexLib student account</p>
        </div>

        <div class="auth-body">
            <div class="alert alert-light border py-2 px-3 small mb-3 text-secondary">
                <i class="bi bi-shield-lock text-primary me-1"></i> Your account credentials are securely stored and never displayed.
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 px-3 small d-flex align-items-center gap-2 mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_URL ?>/register.php" method="POST" autocomplete="off">
                <!-- Full Name -->
                <div class="mb-3">
                    <label for="fullname" class="form-label"><i class="bi bi-person text-secondary me-1"></i> Full Name</label>
                    <input type="text" class="form-control" id="fullname" name="fullname" 
                           placeholder="e.g. Alice Smith" value="<?= htmlspecialchars($fullname) ?>" required>
                </div>

                <div class="row">
                    <!-- Username -->
                    <div class="col-md-6 mb-3">
                        <label for="username" class="form-label"><i class="bi bi-at text-secondary me-1"></i> Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               placeholder="e.g. alicesmith" value="<?= htmlspecialchars($username) ?>" required>
                    </div>

                    <!-- Role Selection -->
                    <div class="col-md-6 mb-3">
                        <span class="form-label d-block"><i class="bi bi-shield-check text-primary me-1"></i> Account Role</span>
                        <div class="form-control bg-light">Student (students)</div>
                        <input type="hidden" name="role" value="students">
                    </div>
                </div>

                <!-- Email -->
                <div class="mb-3">
                    <label for="email" class="form-label"><i class="bi bi-envelope text-secondary me-1"></i> Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="alice.smith@university.edu" value="<?= htmlspecialchars($email) ?>" required>
                </div>

                <div class="row">
                    <!-- Password -->
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label"><i class="bi bi-lock text-secondary me-1"></i> Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Minimum 6 characters" required>
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label"><i class="bi bi-check2-circle text-secondary me-1"></i> Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Re-enter password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-2 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-person-check"></i> Register Account
                </button>
            </form>

            <hr class="my-4">

            <div class="text-center">
                <span class="text-muted small">Already have an account?</span>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline-primary btn-sm ms-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Sign In Here
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
