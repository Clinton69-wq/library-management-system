<?php
/**
 * Comprehensive Automated System Test Suite
 * Validates:
 * 1. Database Connection & Schema
 * 2. Salt & Pepper Security (Unique generated salt per user, HMAC pepper)
 * 3. Two-Step Verification (6-digit OTP generation and validation)
 * 4. Four User Roles ('admin', 'students', 'libarian', 'books') and Role Selection
 * 5. Backups & Cloudflare R2 Records Tracking
 * 6. Non-Destructive Configuration Restore Points (Config changes, data remains untouched)
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

echo "========================================================\n";
echo "      APEXLIB SYSTEM COMPREHENSIVE TEST SUITE           \n";
echo "========================================================\n\n";

$pdo = getDBConnection();
$passed = 0;
$failed = 0;

function assert_test($description, $condition) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $passed++;
    } else {
        echo "[FAIL] " . $description . "\n";
        $failed++;
    }
}

// -------------------------------------------------------------
// TEST 1: Database & 4 User Roles Check
// -------------------------------------------------------------
echo "\n--- 1. Testing Database & User Roles ('admin', 'students', 'libarian', 'books') ---\n";
$stmt = $pdo->query("SELECT DISTINCT role FROM users");
$rolesInDb = $stmt->fetchAll(PDO::FETCH_COLUMN);

assert_test("Database contains 'admin' role", in_array('admin', $rolesInDb));
assert_test("Database contains 'students' role", in_array('students', $rolesInDb));
assert_test("Database contains 'libarian' role", in_array('libarian', $rolesInDb));
assert_test("Database contains 'books' role", in_array('books', $rolesInDb));

// -------------------------------------------------------------
// TEST 2: Salt & Pepper Cryptographic Verification
// -------------------------------------------------------------
echo "\n--- 2. Testing Salt & Pepper Architecture ---\n";
// Fetch salts of all users
$userSalts = $pdo->query("SELECT username, salt, password_hash, role FROM users")->fetchAll();

$allSaltsUnique = (count($userSalts) === count(array_unique(array_column($userSalts, 'salt'))));
assert_test("All generated user salts are unique in database", $allSaltsUnique);

foreach ($userSalts as $u) {
    $hasHexSalt = (strlen($u['salt']) === 32 && ctype_xdigit($u['salt']));
    assert_test("User '{$u['username']}' has a valid 32-hex salt", $hasHexSalt);
}

// Test Pepper verification
$testPlainPassword = 'TestSecurePassword!2026';
$testSalt = generate_user_salt(16);
$testHash = hash_password_salt_pepper($testPlainPassword, $testSalt);

assert_test("Hash created using Salt & Pepper is verified with correct password", verify_password_salt_pepper($testPlainPassword, $testSalt, $testHash));
assert_test("Hash verification fails with incorrect password", !verify_password_salt_pepper('WrongPassword123', $testSalt, $testHash));

// -------------------------------------------------------------
// TEST 3: Two-Step Verification (OTP)
// -------------------------------------------------------------
echo "\n--- 3. Testing 2-Step OTP Authentication ---\n";
$firstUser = $userSalts[0];
$firstUserId = $pdo->query("SELECT id FROM users WHERE username = '{$firstUser['username']}'")->fetchColumn();

$generatedOtp = generate_otp($firstUserId);
assert_test("Generated OTP is exactly 6 digits numeric ({$generatedOtp})", strlen($generatedOtp) === 6 && ctype_digit($generatedOtp));

// Test wrong OTP
$wrongVerify = verify_otp_code($firstUserId, '000000');
assert_test("Verification fails for invalid OTP code", !$wrongVerify['success']);

// Test correct OTP
$correctVerify = verify_otp_code($firstUserId, $generatedOtp);
assert_test("Verification succeeds for valid OTP code", $correctVerify['success']);

// Verify OTP is cleared after use
$clearedOtp = $pdo->query("SELECT otp_code FROM users WHERE id = {$firstUserId}")->fetchColumn();
assert_test("OTP is cleared from database after successful verification", empty($clearedOtp));

// -------------------------------------------------------------
// TEST 4: Backups & Cloudflare Records
// -------------------------------------------------------------
echo "\n--- 4. Testing System Backups & Cloudflare Records ---\n";
$initialBackupsCount = (int)$pdo->query("SELECT COUNT(*) FROM backups")->fetchColumn();
$initialCfCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_backup_records")->fetchColumn();

$backupResult = create_system_backup('Test Runner');
assert_test("Backup created successfully ({$backupResult['filename']})", $backupResult['success']);
assert_test("Backup file exists on disk", file_exists(BACKUP_DIR . '/' . $backupResult['filename']));

$newBackupsCount = (int)$pdo->query("SELECT COUNT(*) FROM backups")->fetchColumn();
$newCfCount = (int)$pdo->query("SELECT COUNT(*) FROM cloudflare_backup_records")->fetchColumn();

assert_test("Backups table count incremented by 1", $newBackupsCount === $initialBackupsCount + 1);
assert_test("Cloudflare backup records count incremented by 1", $newCfCount === $initialCfCount + 1);

// Verify Cloudflare record details
$latestCf = $pdo->query("
    SELECT cloudflare_ray_id, sync_status, response_code
    FROM cloudflare_backup_records
    ORDER BY id DESC
    LIMIT 1
")->fetch();
assert_test("Cloudflare record contains valid Ray ID ({$latestCf['cloudflare_ray_id']})", !empty($latestCf['cloudflare_ray_id']));
assert_test("Cloudflare record status is 'synced' with HTTP 200", $latestCf['sync_status'] === 'synced' && $latestCf['response_code'] === 200);

// -------------------------------------------------------------
// TEST 5: Non-Destructive Configuration Restore Point
// -------------------------------------------------------------
echo "\n--- 5. Testing Non-Destructive Configuration Restore Point ---\n";
// Record current book count and user count
$booksBefore = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$usersBefore = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$loansBefore = (int)$pdo->query("SELECT COUNT(*) FROM book_loans")->fetchColumn();

// 1. Set known config value
update_system_config('max_loan_days', '14');
update_system_config('fine_per_day', '1.50');

// 2. Snapshot Restore Point
$snapshot = create_configuration_restore_point('Test Point A - Normal Policy', 'Standard 14 days and $1.50 fine', 'Test Runner');
$pointId = $snapshot['point_id'];
assert_test("Created Configuration Restore Point #{$pointId}", $snapshot['success']);

// 3. Mutate configuration to drastically different values
update_system_config('max_loan_days', '99');
update_system_config('fine_per_day', '25.00');
assert_test("Configuration mutated to max_loan_days = 99", get_system_config('max_loan_days') === '99');
assert_test("Configuration mutated to fine_per_day = 25.00", get_system_config('fine_per_day') === '25.00');

// 4. Perform Restore from Point
$restoreResult = restore_from_configuration_point($pointId, 'Test Runner');
assert_test("Configuration restore operation succeeded", $restoreResult['success']);

// 5. Verify configuration was restored back to original values!
$restoredDays = get_system_config('max_loan_days');
$restoredFine = get_system_config('fine_per_day');
assert_test("Configuration restored: max_loan_days is back to '14'", $restoredDays === '14');
assert_test("Configuration restored: fine_per_day is back to '1.50'", $restoredFine === '1.50');

// 6. CRITICAL VERIFICATION: Ensure transactional data was NOT modified!
$booksAfter = (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
$usersAfter = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$loansAfter = (int)$pdo->query("SELECT COUNT(*) FROM book_loans")->fetchColumn();

assert_test("Data integrity check: Book records count unchanged ({$booksBefore} -> {$booksAfter})", $booksBefore === $booksAfter);
assert_test("Data integrity check: Users count unchanged ({$usersBefore} -> {$usersAfter})", $usersBefore === $usersAfter);
assert_test("Data integrity check: Loans count unchanged ({$loansBefore} -> {$loansAfter})", $loansBefore === $loansAfter);

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "TEST RESULTS: {$passed} PASSED, {$failed} FAILED\n";
echo "========================================================\n";

if ($failed === 0) {
    echo ">>> ALL REQUIREMENTS VALIDATED SUCCESSFULLY! <<<\n";
} else {
    echo ">>> SOME TESTS FAILED. CHECK LOGS ABOVE. <<<\n";
}
