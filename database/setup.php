<?php
/**
 * Automated Database Installer and Seeder
 * Sets up tables, seeds 4 user roles with Salt & Pepper,
 * creates initial restore points, and generates initial Cloudflare backup records.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

echo "=== APEXLIB DATABASE INITIALIZATION ===\n";

$pdo = getDBConnection();

// 1. Run Schema SQL
$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    die("Error: schema.sql not found at $schemaFile\n");
}

$sql = file_get_contents($schemaFile);
$pdo->exec($sql);
echo "[OK] Base tables and configurations created successfully.\n";

// 2. Seed Default Accounts for all 4 Roles
$defaultUsers = [
    [
        'fullname' => 'System Administrator',
        'username' => 'admin',
        'email'    => 'admin@apexlib.local',
        'role'     => 'admin',
        'password' => 'admin123'
    ],
    [
        'fullname' => 'Sarah Johnson (Student)',
        'username' => 'student1',
        'email'    => 'sarah.j@student.apexlib.local',
        'role'     => 'students',
        'password' => 'student123'
    ],
    [
        'fullname' => 'David Miller (Head Librarian)',
        'username' => 'librarian1',
        'email'    => 'david.m@librarian.apexlib.local',
        'role'     => 'libarian',
        'password' => 'lib123'
    ],
    [
        'fullname' => 'Elena Rostova (Books Cataloger)',
        'username' => 'bookkeeper1',
        'email'    => 'elena.r@books.apexlib.local',
        'role'     => 'books',
        'password' => 'books123'
    ]
];

echo "\n--- Seeding Users with Salt & Pepper --- \n";
$stmt = $pdo->prepare("INSERT INTO users (fullname, username, email, role, password_hash, salt, two_factor_enabled) VALUES (?, ?, ?, ?, ?, ?, 1)");
foreach ($defaultUsers as $u) {
    // Generate unique salt per user
    $salt = generate_user_salt(16);
    // Hash using salt + SYSTEM_PEPPER
    $hash = hash_password_salt_pepper($u['password'], $salt);

    $stmt->execute([$u['fullname'], $u['username'], $u['email'], $u['role'], $hash, $salt]);
    $userId = $pdo->lastInsertId();

    echo "User created: [Role: {$u['role']}] Username: {$u['username']}\n";
}

// 3. Seed sample book loans for student1
$studentStmt = $pdo->query("SELECT id FROM users WHERE username = 'student1'");
$studentId = $studentStmt->fetchColumn();

$bookStmt = $pdo->query("SELECT id FROM books LIMIT 2");
$books = $bookStmt->fetchAll(PDO::FETCH_COLUMN);

if ($studentId && count($books) >= 2) {
    $borrowDate1 = date('Y-m-d', strtotime('-5 days'));
    $dueDate1 = date('Y-m-d', strtotime('+9 days'));
    $loan1 = $pdo->prepare("INSERT INTO book_loans (book_id, user_id, issued_by, borrow_date, due_date, status) VALUES (?, ?, 'David Miller (Librarian)', ?, ?, 'borrowed')");
    $loan1->execute([$books[0], $studentId, $borrowDate1, $dueDate1]);

    // An overdue book loan for demonstration
    $borrowDate2 = date('Y-m-d', strtotime('-20 days'));
    $dueDate2 = date('Y-m-d', strtotime('-6 days'));
    $loan2 = $pdo->prepare("INSERT INTO book_loans (book_id, user_id, issued_by, borrow_date, due_date, status, fine_amount, notes) VALUES (?, ?, 'David Miller (Librarian)', ?, ?, 'overdue', 9.00, 'Overdue by 6 days at $1.50/day')");
    $loan2->execute([$books[1], $studentId, $borrowDate2, $dueDate2]);

    echo "[OK] Sample active and overdue loans seeded.\n";
}

// 4. Create Initial Configuration Restore Point
$resPoint = create_configuration_restore_point(
    'Baseline Academic Policy (Fall 2026)',
    'Standard university borrowing limits: 14-day loan window, $1.50/day overdue fine, 10-minute OTP expiration.',
    'System Administrator'
);
echo "[OK] Created initial Configuration Restore Point #{$resPoint['point_id']} ({$resPoint['name']})\n";

// 5. Create Initial Backup and Sync to Cloudflare Records
$backup = create_system_backup('System Administrator');
echo "[OK] Created initial system backup: {$backup['filename']} ({$backup['size']} bytes)\n";
echo "[OK] Recorded Cloudflare R2 backup record: Ray ID [{$backup['cf_ray_id']}] -> Bucket: apex-library-r2-vault\n";

echo "\n=== SETUP COMPLETED SUCCESSFULLY ===\n";
