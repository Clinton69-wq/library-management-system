<?php
/**
 * Global Header Component
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$currentUser = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500;600&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<?php if (is_logged_in() && (!isset($layoutType) || $layoutType !== 'admin')): ?>
<!-- Top Navigation for Standard Users (students, libarian, books) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-2 border-bottom border-secondary">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" href="<?= get_dashboard_url_for_role($currentUser['role']) ?>">
            <i class="bi bi-book-half text-primary fs-4"></i>
            <span>ApexLib <span class="badge bg-secondary text-light fs-8 fw-normal ms-1">Cloud</span></span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#userNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="userNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'dashboard_' . $currentUser['role'] . '.php') ? 'active' : '' ?>" href="<?= get_dashboard_url_for_role($currentUser['role']) ?>">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <?php if ($currentUser['role'] === 'students'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#catalog-section"><i class="bi bi-search me-1"></i> Browse Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#my-loans-section"><i class="bi bi-journal-check me-1"></i> My Loans</a>
                    </li>
                <?php elseif ($currentUser['role'] === 'libarian'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#circulation-desk"><i class="bi bi-arrow-left-right me-1"></i> Circulation Desk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#loans-record"><i class="bi bi-clock-history me-1"></i> Loan History</a>
                    </li>
                <?php elseif ($currentUser['role'] === 'books'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="#inventory-list"><i class="bi bi-boxes me-1"></i> Book Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#add-book-section"><i class="bi bi-plus-circle me-1"></i> Add Title</a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/profile.php">
                        <i class="bi bi-shield-lock me-1"></i> Security Profile
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <div class="d-none d-md-flex align-items-center gap-2">
                    <span class="role-pill role-<?= htmlspecialchars($currentUser['role']) ?>">
                        <?= htmlspecialchars(ALLOWED_ROLES[$currentUser['role']] ?? $currentUser['role']) ?>
                    </span>
                    <span class="text-light fw-medium fs-7">
                        <?= htmlspecialchars($currentUser['fullname']) ?>
                    </span>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm px-3 d-flex align-items-center gap-1">
                    <i class="bi bi-box-arrow-right"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Flash Notifications Banner Area -->
<div class="container-fluid px-4 mt-3">
    <?php if ($flashSuccess = get_flash('success')): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= $flashSuccess ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><?= $flashError ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($flashInfo = get_flash('info')): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div><?= $flashInfo ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
</div>

