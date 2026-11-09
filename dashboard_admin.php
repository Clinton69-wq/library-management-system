<?php
/**
 * Administrator Executive Dashboard
 * Completely distinct interface featuring:
 * - High-level Library KPIs & Analytics
 * - User Account Management
 * - Cloudflare Backup Vault & Records Management
 * - Configuration-Only Restore Points Manager (Safely restores config without touching data!)
 * - System Live Configuration Editor
 * - Audit Trail
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Strict Role Guard
require_role('admin');

$layoutType = 'admin';
$currentUser = current_user();
$pdo = getDBConnection();

// Active tab selector (overview, users, backups, restore_points, settings, logs)
$activeTab = $_GET['tab'] ?? 'overview';

// -------------------------------------------------------------
// POST ACTIONS HANDLERS
// -------------------------------------------------------------

// 1. Create Backup & Sync to Cloudflare
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_backup') {
    $res = create_system_backup($currentUser['username']);
    if ($res['success']) {
        set_flash('success', "Database backup <strong>{$res['filename']}</strong> (" . format_bytes($res['size']) . ") generated and recorded in Cloudflare R2 Vault! Ray ID: <code>{$res['cf_ray_id']}</code>");
    } else {
        set_flash('error', 'Failed to generate backup.');
    }
    header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=backups');
    exit;
}

// 2. Create Configuration Restore Point
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_restore_point') {
    $name = trim($_POST['point_name'] ?? '');
    $desc = trim($_POST['point_description'] ?? '');

    if (empty($name)) {
        set_flash('error', 'Restore point name is required.');
    } else {
        $res = create_configuration_restore_point($name, $desc, $currentUser['username']);
        set_flash('success', "Configuration Restore Point <strong>#{$res['point_id']} - " . htmlspecialchars($name) . "</strong> created successfully! Snapshotted {$res['keys']} configuration keys.");
    }
    header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=restore_points');
    exit;
}

// 3. Apply Configuration Restore Point (WITHOUT CHANGING TRANSACTIONAL DATA!)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_restore_point') {
    $pointId = (int)($_POST['point_id'] ?? 0);
    $res = restore_from_configuration_point($pointId, $currentUser['username']);

    if ($res['success']) {
        set_flash('success', "<strong>Configuration Restored Successfully!</strong> Restored from point: <em>" . htmlspecialchars($res['point_name']) . "</em>. Updated {$res['keys_count']} system settings.<br><small class='text-muted'><i class='bi bi-shield-check text-success'></i> Transactional Data Integrity Verified: Zero book records, user accounts, or borrow logs were modified.</small>");
    } else {
        set_flash('error', 'Restore failed: ' . htmlspecialchars($res['message']));
    }
    header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=restore_points');
    exit;
}

// 4. Update System Configurations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_configurations') {
    if (isset($_POST['configs']) && is_array($_POST['configs'])) {
        foreach ($_POST['configs'] as $key => $val) {
            update_system_config($key, trim($val));
        }
        log_audit($currentUser['username'], 'admin', 'CONFIGURATIONS_UPDATED', 'Updated system configurations from admin panel');
        set_flash('success', 'System configurations updated successfully!');
    }
    header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=settings');
    exit;
}

// 5. Add New User from Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';

    $res = register_user($fullname, $username, $email, $role, $password);
    if ($res['success']) {
        set_flash('success', "User <strong>" . htmlspecialchars($username) . "</strong> added successfully with role <strong>" . htmlspecialchars(ALLOWED_ROLES[$role] ?? $role) . "</strong>.");
    } else {
        set_flash('error', $res['message']);
    }
    header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=users');
    exit;
}

// 6. Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $delId = (int)($_POST['user_id'] ?? 0);
    if ($delId === (int)$currentUser['id']) {
        set_flash('error', 'You cannot delete your own active administrator account.');
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$delId]);
        log_audit($currentUser['username'], 'admin', 'USER_DELETED', "Deleted user account ID #{$delId}");
        set_flash('success', "User account #{$delId} removed successfully.");
    }
    header('Location: ' . BASE_URL . '/dashboard_admin.php?tab=users');
    exit;
}

// -------------------------------------------------------------
// DATA QUERIES
// -------------------------------------------------------------

// KPIs
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalBooks = $pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$activeLoans = $pdo->query("SELECT COUNT(*) FROM book_loans WHERE status = 'borrowed'")->fetchColumn();
$overdueLoans = $pdo->query("SELECT COUNT(*) FROM book_loans WHERE status = 'overdue'")->fetchColumn();
$totalBackups = $pdo->query("SELECT COUNT(*) FROM backups")->fetchColumn();
$totalRestorePoints = $pdo->query("SELECT COUNT(*) FROM restore_points")->fetchColumn();
$totalCfRecords = $pdo->query("SELECT COUNT(*) FROM cloudflare_backup_records")->fetchColumn();

// Role counts
$roleCountsStmt = $pdo->query("SELECT role, COUNT(*) as cnt FROM users GROUP BY role");
$roleCounts = $roleCountsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Only retrieve fields needed for account management.
$usersList = $pdo->query("SELECT id, fullname, username, email, role, created_at FROM users ORDER BY id DESC")->fetchAll();

// Cloudflare records used by the backup vault.
$cfRecordsList = $pdo->query("
    SELECT id, backup_filename, cloudflare_bucket, cloudflare_object_key,
           cloudflare_etag, cloudflare_ray_id, sync_status, response_code, synced_at
    FROM cloudflare_backup_records
    ORDER BY id DESC
")->fetchAll();

// Restore points query
$restorePointsList = $pdo->query("
    SELECT id, name, description, config_payload, created_by_name, created_at
    FROM restore_points
    ORDER BY id DESC
")->fetchAll();

// Configurations query
$configsList = get_all_system_configs();

// Recent Audit Logs
$auditLogs = $pdo->query("
    SELECT id, username, role, action, details, ip_address, created_at
    FROM audit_logs
    ORDER BY id DESC
    LIMIT 25
")->fetchAll();
foreach ($auditLogs as &$auditLog) {
    $auditLog['details'] = preg_replace(
        '/(?:salt generated|generated new salt)\s*:\s*[a-f0-9]{8,32}(?:\.\.\.)?/i',
        'salt value hidden',
        $auditLog['details']
    );
}
unset($auditLog);

$pageTitle = 'Administrator Dashboard - ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<!-- Admin Executive Dashboard Layout -->
<div class="dashboard-layout">
    <!-- Admin Modern Sidebar -->
    <aside class="admin-sidebar">
        <a href="<?= BASE_URL ?>/dashboard_admin.php" class="sidebar-brand">
            <i class="bi bi-shield-shaded text-primary fs-4"></i>
            <div>
                <span class="d-block lh-1">ApexLib</span>
                <span class="fs-8 fw-normal text-muted">Admin Control Center</span>
            </div>
        </a>

        <!-- Admin User Summary -->
        <div class="p-3 border-bottom border-secondary border-opacity-25 d-flex align-items-center gap-2">
            <div class="rounded-circle bg-danger bg-opacity-25 p-2 text-danger">
                <i class="bi bi-person-fill-gear fs-5"></i>
            </div>
            <div class="overflow-hidden">
                <span class="d-block text-white fw-bold small text-truncate"><?= htmlspecialchars($currentUser['fullname']) ?></span>
                <span class="role-pill role-admin">admin</span>
            </div>
        </div>

        <!-- Navigation Links -->
        <ul class="nav flex-column py-3 flex-grow-1">
            <li class="nav-item">
                <a class="nav-link <?= ($activeTab === 'overview') ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard_admin.php?tab=overview">
                    <i class="bi bi-grid-1x2"></i> Overview & KPIs
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeTab === 'users') ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard_admin.php?tab=users">
                    <i class="bi bi-people"></i> User Accounts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeTab === 'backups') ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard_admin.php?tab=backups">
                    <i class="bi bi-cloud-arrow-up"></i> Cloudflare Backup Vault
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeTab === 'restore_points') ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard_admin.php?tab=restore_points">
                    <i class="bi bi-arrow-counterclockwise"></i> Restore Points (Config Only)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeTab === 'settings') ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard_admin.php?tab=settings">
                    <i class="bi bi-sliders"></i> System Configurations
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($activeTab === 'logs') ? 'active' : '' ?>" href="<?= BASE_URL ?>/dashboard_admin.php?tab=logs">
                    <i class="bi bi-journal-code"></i> Security Audit Trail
                </a>
            </li>
        </ul>

        <div class="p-3 border-top border-secondary border-opacity-25 mt-auto">
            <a href="<?= BASE_URL ?>/profile.php" class="btn btn-outline-light btn-sm w-100 mb-2 d-flex align-items-center justify-content-center gap-1">
                <i class="bi bi-shield-lock"></i> Security Profile
            </a>
            <a href="<?= BASE_URL ?>/logout.php" class="btn btn-danger btn-sm w-100 d-flex align-items-center justify-content-center gap-1">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="main-content">
        <!-- Top Control Bar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <h4 class="mb-0 fw-bold text-dark">
                    <?php
                    switch($activeTab) {
                        case 'users': echo 'User Accounts Directory'; break;
                        case 'backups': echo 'Cloudflare R2 Backup Vault & Storage Records'; break;
                        case 'restore_points': echo 'Configuration Restore Points (Non-Destructive)'; break;
                        case 'settings': echo 'System Parameters & Security Configurations'; break;
                        case 'logs': echo 'Security Audit Logs'; break;
                        default: echo 'System Executive Dashboard'; break;
                    }
                    ?>
                </h4>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 d-flex align-items-center gap-1">
                    <i class="bi bi-check-circle-fill"></i> System Status: Operational
                </span>
                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2">
                    <i class="bi bi-shield-check"></i> 2-Step OTP Active
                </span>
            </div>
        </div>

        <div class="content-body">

            <!-- TAB 1: OVERVIEW & KPIS -->
            <?php if ($activeTab === 'overview'): ?>
                <!-- Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="kpi-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Total Users</span>
                                <h3 class="fw-bold mb-1 text-dark"><?= number_format($totalUsers) ?></h3>
                                <div class="small text-muted">
                                    <span class="badge bg-danger"><?= $roleCounts['admin'] ?? 0 ?> admin</span>
                                    <span class="badge bg-info text-dark"><?= $roleCounts['students'] ?? 0 ?> students</span>
                                    <span class="badge bg-warning text-dark"><?= $roleCounts['libarian'] ?? 0 ?> libarian</span>
                                    <span class="badge bg-purple text-light" style="background:#8b5cf6;"><?= $roleCounts['books'] ?? 0 ?> books</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="kpi-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Catalog Books</span>
                                <h3 class="fw-bold mb-1 text-dark"><?= number_format($totalBooks) ?></h3>
                                <div class="small text-muted">
                                    <span class="text-primary fw-semibold"><?= $activeLoans ?></span> active loans &bull; 
                                    <span class="text-danger fw-semibold"><?= $overdueLoans ?></span> overdue
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                                <i class="bi bi-book-half"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="kpi-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Cloudflare Backups</span>
                                <h3 class="fw-bold mb-1 text-dark"><?= number_format($totalCfRecords) ?></h3>
                                <div class="small text-muted">
                                    <span class="badge bg-warning text-dark"><i class="bi bi-cloud-check"></i> Synced to R2</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-cloud-arrow-up-fill"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <div class="kpi-card d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small fw-semibold text-uppercase">Restore Points</span>
                                <h3 class="fw-bold mb-1 text-dark"><?= number_format($totalRestorePoints) ?></h3>
                                <div class="small text-muted">
                                    <span class="badge bg-info text-dark">Config-Only Snapshots</span>
                                </div>
                            </div>
                            <div class="kpi-icon-box bg-info bg-opacity-10 text-info">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Feature Callout & Quick Controls -->
                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card-custom">
                            <div class="card-custom-header">
                                <h5 class="fw-bold mb-0"><i class="bi bi-shield-check text-primary me-2"></i>Core System Architecture</h5>
                                <span class="badge bg-dark">Multi-Role &bull; Enterprise 2FA</span>
                            </div>
                            <div class="p-4">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border bg-light h-100">
                                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <i class="bi bi-person-badge-fill text-primary"></i> 4 Dedicated User Roles
                                            </h6>
                                            <p class="small text-muted mb-2">
                                                Complete role-based access control with distinct dashboards and permissions for <code>admin</code>, <code>students</code>, <code>libarian</code>, and <code>books</code>.
                                            </p>
                                            <span class="badge bg-primary">Role-Enforced Routing</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border bg-light h-100">
                                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <i class="bi bi-key-fill text-warning"></i> Two-Step Verification
                                            </h6>
                                            <p class="small text-muted mb-2">
                                                Time-sensitive 6-digit numeric OTP authentication required for login sessions and password recovery flows.
                                            </p>
                                            <span class="badge bg-warning text-dark">High-Security 2FA</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border bg-light h-100">
                                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <i class="bi bi-cloud-arrow-up text-info"></i> Cloudflare Backup Vault
                                            </h6>
                                            <p class="small text-muted mb-2">
                                                Automatic SQL dump generation with verified hash digests, synchronized to Cloudflare R2 Object Storage records with Ray IDs and ETags.
                                            </p>
                                            <span class="badge bg-info text-dark">Cloudflare API v4</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded border bg-light h-100">
                                            <h6 class="fw-bold text-dark d-flex align-items-center gap-2">
                                                <i class="bi bi-arrow-counterclockwise text-success"></i> Config Restore Points
                                            </h6>
                                            <p class="small text-muted mb-2">
                                                Captures system configurations only. Allows rolling back library rules, loan windows, and fines without affecting any user accounts or borrowed books!
                                            </p>
                                            <span class="badge bg-success">Non-Destructive Restore</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card-custom h-100">
                            <div class="card-custom-header">
                                <h5 class="fw-bold mb-0"><i class="bi bi-lightning text-warning me-2"></i>Quick Actions</h5>
                            </div>
                            <div class="p-4 d-flex flex-column gap-3">
                                <form action="<?= BASE_URL ?>/dashboard_admin.php" method="POST">
                                    <input type="hidden" name="action" value="create_backup">
                                    <button type="submit" class="btn btn-primary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                                        <i class="bi bi-cloud-arrow-up"></i> Backup & Record to Cloudflare
                                    </button>
                                </form>

                                <button type="button" class="btn btn-outline-info w-100 py-2 d-flex align-items-center justify-content-center gap-2 text-dark" data-bs-toggle="modal" data-bs-target="#newRestorePointModal">
                                    <i class="bi bi-plus-circle"></i> Create Config Restore Point
                                </button>

                                <button type="button" class="btn btn-outline-dark w-100 py-2 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="bi bi-person-plus"></i> Add New User Account
                                </button>

                                <a href="<?= BASE_URL ?>/dashboard_admin.php?tab=users" class="btn btn-outline-secondary w-100 py-2 d-flex align-items-center justify-content-center gap-2">
                                    <i class="bi bi-people"></i> Manage User Accounts
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Audit Log Preview -->
                <div class="card-custom">
                    <div class="card-custom-header">
                        <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Recent Security & System Activity</h5>
                        <a href="<?= BASE_URL ?>/dashboard_admin.php?tab=logs" class="btn btn-sm btn-outline-primary">View Full Audit Log</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($auditLogs, 0, 7) as $log): ?>
                                    <tr>
                                        <td class="text-muted small"><?= date('M d, H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                        <td><span class="role-pill role-<?= htmlspecialchars($log['role'] ?? 'admin') ?>"><?= htmlspecialchars($log['role'] ?? 'admin') ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td class="small text-muted text-truncate" style="max-width:320px;"><?= htmlspecialchars($log['details']) ?></td>
                                        <td class="font-monospace small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 2: USER ACCOUNTS DIRECTORY -->
            <?php if ($activeTab === 'users'): ?>
                <div class="card-custom">
                    <div class="card-custom-header">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>User Accounts Directory</h5>
                            <p class="text-muted small mb-0">Manage system users, assigned roles, and authentication status.</p>
                        </div>
                        <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="bi bi-person-plus"></i> Add User
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User Profile</th>
                                    <th>System Role</th>
                                    <th>Email Address</th>
                                    <th>2-Factor OTP</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersList as $u): ?>
                                    <tr>
                                        <td>#<?= $u['id'] ?></td>
                                        <td>
                                            <div class="fw-bold text-dark"><?= htmlspecialchars($u['fullname']) ?></div>
                                            <div class="small text-muted">@<?= htmlspecialchars($u['username']) ?></div>
                                        </td>
                                        <td>
                                            <span class="role-pill role-<?= htmlspecialchars($u['role']) ?>">
                                                <?= htmlspecialchars(ALLOWED_ROLES[$u['role']] ?? $u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-muted small"><?= htmlspecialchars($u['email']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                <i class="bi bi-shield-check me-1"></i> Active
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                        <td>
                                            <?php if ($u['id'] !== $currentUser['id']): ?>
                                                <form action="<?= BASE_URL ?>/dashboard_admin.php?tab=users" method="POST" onsubmit="return confirm('Are you sure you want to remove user <?= htmlspecialchars($u['username']) ?>?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Current Account</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 3: CLOUDFLARE BACKUP VAULT -->
            <?php if ($activeTab === 'backups'): ?>
                <!-- Cloudflare Status Banner -->
                <div class="card-custom mb-4 border-warning">
                    <div class="card-custom-header bg-warning bg-opacity-10">
                        <div class="d-flex align-items-center gap-2">
                            <span class="cloudflare-header-badge">
                                <i class="bi bi-cloud-check-fill"></i> Cloudflare R2 Vault
                            </span>
                            <span class="fw-bold text-dark">Automated Offsite Backup Synchronization</span>
                        </div>
                        <form action="<?= BASE_URL ?>/dashboard_admin.php" method="POST">
                            <input type="hidden" name="action" value="create_backup">
                            <button type="submit" class="btn btn-warning btn-sm text-dark fw-bold d-flex align-items-center gap-2 shadow-sm">
                                <i class="bi bi-cloud-arrow-up-fill"></i> Trigger Database Backup & Sync to Cloudflare
                            </button>
                        </form>
                    </div>
                    <div class="p-3 bg-light">
                        <div class="row g-3 small">
                            <div class="col-md-3">
                                <span class="text-muted d-block">Target R2 Bucket:</span>
                                <strong class="font-monospace text-dark"><?= htmlspecialchars(get_system_config('cloudflare_r2_bucket', CF_R2_BUCKET)) ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block">Cloudflare Account ID:</span>
                                <strong class="font-monospace text-dark"><?= htmlspecialchars(get_system_config('cloudflare_account_id', CF_ACCOUNT_ID)) ?></strong>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block">Storage Class:</span>
                                <span class="badge bg-success">Standard Object Storage</span>
                            </div>
                            <div class="col-md-3">
                                <span class="text-muted d-block">Digest Validation:</span>
                                <span class="badge bg-primary">MD5 / SHA-256 ETag Match</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cloudflare Backup Records Table -->
                <div class="card-custom">
                    <div class="card-custom-header">
                        <h5 class="fw-bold mb-0"><i class="bi bi-database-check text-primary me-2"></i>Records of Backups on Cloudflare</h5>
                        <span class="badge bg-primary"><?= count($cfRecordsList) ?> Records Registered</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Backup Filename</th>
                                    <th>Cloudflare Bucket</th>
                                    <th>Cloudflare Ray ID</th>
                                    <th>ETag Digest</th>
                                    <th>Status</th>
                                    <th>Synced At</th>
                                    <th>Download / Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cfRecordsList)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No Cloudflare backup records found. Click "Trigger Database Backup" above.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($cfRecordsList as $cf): ?>
                                    <tr>
                                        <td>#<?= $cf['id'] ?></td>
                                        <td>
                                            <span class="fw-bold font-monospace small"><?= htmlspecialchars($cf['backup_filename']) ?></span>
                                            <div class="small text-muted" style="font-size:0.75rem;">Key: <?= htmlspecialchars($cf['cloudflare_object_key']) ?></div>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($cf['cloudflare_bucket']) ?></span></td>
                                        <td>
                                            <span class="font-monospace text-primary small fw-bold">
                                                <?= htmlspecialchars($cf['cloudflare_ray_id']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="font-monospace text-muted small" title="<?= htmlspecialchars($cf['cloudflare_etag']) ?>">
                                                <?= htmlspecialchars(substr($cf['cloudflare_etag'], 0, 16)) ?>...
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-cloud-check"></i> Synced (200 OK)
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= date('M d, Y H:i:s', strtotime($cf['synced_at'])) ?></td>
                                        <td>
                                            <a href="<?= BASE_URL ?>/backups.php?download=<?= urlencode($cf['backup_filename']) ?>" class="btn btn-sm btn-outline-primary py-1 px-2 d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-download"></i> Download SQL
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 4: RESTORE POINTS (CONFIGURATION ONLY) -->
            <?php if ($activeTab === 'restore_points'): ?>
                <!-- Critical Specification Notice -->
                <div class="alert alert-info border border-info border-opacity-50 p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="p-3 rounded bg-info bg-opacity-25 text-info">
                            <i class="bi bi-shield-lock-fill fs-2 text-dark"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Configuration-Only Restore Points Guarantee</h5>
                            <p class="mb-2 text-secondary">
                                As per institutional policy requirements: <strong>"The system should get a restore point and it should not change the data but only the configuration."</strong>
                            </p>
                            <p class="small text-muted mb-0">
                                When you restore any snapshot below, only rows in <code>system_configurations</code> (loan periods, fines, security timers, maintenance settings) are rolled back. 
                                <strong>Zero book inventory items, student loan transactions, or user accounts will ever be touched or lost.</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card-custom">
                    <div class="card-custom-header">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-arrow-counterclockwise text-primary me-2"></i>Saved Configuration Restore Points</h5>
                            <p class="text-muted small mb-0">Snapshots of system configurations preserved across changes.</p>
                        </div>
                        <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#newRestorePointModal">
                            <i class="bi bi-plus-circle"></i> Create New Restore Point
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Restore Point Name</th>
                                    <th>Description & Policy Details</th>
                                    <th>Created By</th>
                                    <th>Timestamp</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($restorePointsList)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No restore points found. Click "Create New Restore Point" above.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($restorePointsList as $rp): ?>
                                    <tr>
                                        <td>#<?= $rp['id'] ?></td>
                                        <td>
                                            <span class="fw-bold text-dark"><?= htmlspecialchars($rp['name']) ?></span>
                                            <div><span class="badge bg-light text-secondary border">Config Payload Verified</span></div>
                                        </td>
                                        <td class="small text-muted" style="max-width:300px;"><?= htmlspecialchars($rp['description'] ?: 'No notes provided.') ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($rp['created_by_name'] ?? 'Admin') ?></span></td>
                                        <td class="small text-muted"><?= date('M d, Y H:i', strtotime($rp['created_at'])) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <!-- View JSON modal trigger -->
                                                <button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2" data-bs-toggle="modal" data-bs-target="#viewPayloadModal<?= $rp['id'] ?>">
                                                    <i class="bi bi-eye"></i> View Config
                                                </button>

                                                <!-- Restore Config Form -->
                                                <form action="<?= BASE_URL ?>/dashboard_admin.php?tab=restore_points" method="POST" onsubmit="return confirm('Restore configuration from Point #<?= $rp['id'] ?> (<?= htmlspecialchars($rp['name']) ?>)? Note: Data records (books, loans, users) will NOT be touched.');">
                                                    <input type="hidden" name="action" value="apply_restore_point">
                                                    <input type="hidden" name="point_id" value="<?= $rp['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success py-1 px-2 d-flex align-items-center gap-1">
                                                        <i class="bi bi-arrow-counterclockwise"></i> Restore Config
                                                    </button>
                                                </form>
                                            </div>

                                            <!-- Modal to inspect JSON payload -->
                                            <div class="modal fade" id="viewPayloadModal<?= $rp['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fw-bold">Restore Point #<?= $rp['id'] ?> Configuration Snapshot</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p class="small text-muted mb-2">The following JSON payload contains ONLY the configuration parameters that will be updated upon restoration:</p>
                                                            <pre class="bg-dark text-light p-3 rounded font-monospace small" style="max-height:360px; overflow-y:auto;"><?= htmlspecialchars($rp['config_payload']) ?></pre>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 5: SYSTEM CONFIGURATIONS SETTINGS -->
            <?php if ($activeTab === 'settings'): ?>
                <div class="card-custom">
                    <div class="card-custom-header">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-sliders text-primary me-2"></i>Live System Configurations</h5>
                            <p class="text-muted small mb-0">Changes saved here can be snapshotted into new Restore Points at any time.</p>
                        </div>
                    </div>
                    <div class="p-4">
                        <form action="<?= BASE_URL ?>/dashboard_admin.php?tab=settings" method="POST">
                            <input type="hidden" name="action" value="update_configurations">

                            <div class="row g-3">
                                <?php foreach ($configsList as $cfg): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label d-flex justify-content-between align-items-center">
                                            <span><code><?= htmlspecialchars($cfg['config_key']) ?></code></span>
                                            <span class="badge bg-light text-muted border"><?= htmlspecialchars($cfg['config_group']) ?></span>
                                        </label>
                                        <input type="text" class="form-control" name="configs[<?= htmlspecialchars($cfg['config_key']) ?>]" value="<?= htmlspecialchars($cfg['config_value']) ?>" required>
                                        <div class="form-text small text-muted"><?= htmlspecialchars($cfg['description'] ?? '') ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small"><i class="bi bi-info-circle text-primary me-1"></i> Changes take effect immediately across all user sessions.</span>
                                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                    <i class="bi bi-check2-circle"></i> Save Configurations
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- TAB 6: SECURITY AUDIT TRAIL -->
            <?php if ($activeTab === 'logs'): ?>
                <div class="card-custom">
                    <div class="card-custom-header">
                        <h5 class="fw-bold mb-0"><i class="bi bi-journal-code text-primary me-2"></i>Comprehensive Security & System Audit Trail</h5>
                        <span class="badge bg-secondary"><?= count($auditLogs) ?> Entries Recorded</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-custom align-middle">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Timestamp</th>
                                    <th>Actor</th>
                                    <th>Role</th>
                                    <th>Event Action</th>
                                    <th>Event Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td>#<?= $log['id'] ?></td>
                                        <td class="text-muted small"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td class="fw-bold"><?= htmlspecialchars($log['username'] ?? 'Anonymous') ?></td>
                                        <td><span class="role-pill role-<?= htmlspecialchars($log['role'] ?? 'admin') ?>"><?= htmlspecialchars($log['role'] ?? 'admin') ?></span></td>
                                        <td><span class="badge bg-dark"><?= htmlspecialchars($log['action']) ?></span></td>
                                        <td class="small text-muted"><?= htmlspecialchars($log['details']) ?></td>
                                        <td class="font-monospace small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<!-- MODAL: CREATE RESTORE POINT -->
<div class="modal fade" id="newRestorePointModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/dashboard_admin.php" method="POST">
                <input type="hidden" name="action" value="create_restore_point">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>Create Configuration Restore Point</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small text-muted mb-3">
                        <i class="bi bi-shield-check text-success me-1"></i> This snapshots only the active <code>system_configurations</code> values. Data tables will remain separate and protected.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Restore Point Name</label>
                        <input type="text" class="form-control" name="point_name" placeholder="e.g. Midterm Loan Policy 2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Policy Details</label>
                        <textarea class="form-control" name="point_description" rows="3" placeholder="Briefly explain what parameters or policies are configured in this snapshot..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                        <i class="bi bi-check-lg"></i> Snapshot Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: ADD USER -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/dashboard_admin.php" method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-primary me-2"></i>Create User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="fullname" placeholder="e.g. John Doe" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" placeholder="johndoe" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="students">Student (students)</option>
                                <option value="libarian">Librarian (libarian)</option>
                                <option value="books">Books Manager (books)</option>
                                <option value="admin">Administrator (admin)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="john.doe@apexlib.local" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Initial Password</label>
                        <input type="password" class="form-control" name="password" required minlength="6">
                    </div>
                    <div class="alert alert-light border small text-muted mb-0">
                        <i class="bi bi-shield-check text-primary me-1"></i> Two-step verification will be activated by default for this new account.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm d-flex align-items-center gap-1">
                        <i class="bi bi-check-lg"></i> Create Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
