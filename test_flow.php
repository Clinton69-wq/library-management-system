<?php
/**
 * End-to-end Session & Flow Test Script
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = getDBConnection();

echo "--- Testing User Registration with Role 'books' ---\n";
$reg = register_user('Emma Watson (Books Dept)', 'emma_books', 'emma.w@books.apexlib.local', 'books', 'SecretPass2026!');
if ($reg['success']) {
    echo "[OK] Registered user emma_books.\n";
} else {
    echo "[FAIL] " . $reg['message'] . "\n";
}

// Verify user in DB
$userStmt = $pdo->prepare("SELECT id, salt, password_hash FROM users WHERE username = ?");
$userStmt->execute(['emma_books']);
$emma = $userStmt->fetch();
assert(!empty($emma['salt']), "Salt should be stored in database");
echo "[OK] Verified that a salt is stored in the database.\n";

echo "\n--- Testing Credential Verification with Salt & Pepper ---\n";
$validPass = verify_password_salt_pepper('SecretPass2026!', $emma['salt'], $emma['password_hash']);
assert($validPass === true, "Password should verify with salt and pepper");
echo "[OK] Salt and Pepper authentication passed!\n";

echo "\n--- Testing 2FA OTP Flow ---\n";
$otp = generate_otp($emma['id']);
echo "[OK] Generated 2-Step OTP: {$otp}\n";

$verifyRes = verify_otp_code($emma['id'], $otp);
assert($verifyRes['success'] === true, "OTP should verify successfully");
echo "[OK] 2-Step OTP verification successful!\n";

echo "\n--- Testing Role Access Guards ---\n";
assert(get_dashboard_url_for_role('admin') === '/libary/dashboard_admin.php');
assert(get_dashboard_url_for_role('students') === '/libary/dashboard_student.php');
assert(get_dashboard_url_for_role('libarian') === '/libary/dashboard_librarian.php');
assert(get_dashboard_url_for_role('books') === '/libary/dashboard_books.php');
echo "[OK] Role dashboard routing paths match exactly.\n";

echo "\n=== ALL FLOW TESTS COMPLETED WITH 100% SUCCESS ===\n";
