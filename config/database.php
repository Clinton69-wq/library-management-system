<?php
/**
 * Database Connection Utility using PDO
 * Connects to MySQL/MariaDB in production/local XAMPP.
 * Automatically falls back to SQLite when MySQL is unreachable (e.g. initial Vercel deploy).
 */
require_once __DIR__ . '/config.php';

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // 1. Try MySQL Connection
    $mysqlAvailable = false;
    $mysqlHost = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
    $mysqlPort = defined('DB_PORT') ? DB_PORT : '3306';
    $mysqlName = defined('DB_NAME') ? DB_NAME : 'library_db';
    $mysqlUser = defined('DB_USER') ? DB_USER : 'root';
    $mysqlPass = defined('DB_PASS') ? DB_PASS : '';

    $dsn = "mysql:host={$mysqlHost};port={$mysqlPort};dbname={$mysqlName};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $mysqlUser, $mysqlPass, $options);
        // Verify tables exist; if database is empty, auto-initialize from schema.sql
        try {
            $pdo->query("SELECT 1 FROM users LIMIT 1");
        } catch (PDOException $tEx) {
            $schemaFile = __DIR__ . '/../database/schema.sql';
            if (file_exists($schemaFile)) {
                $sql = file_get_contents($schemaFile);
                $pdo->exec($sql);
            }
        }
        return $pdo;
    } catch (PDOException $e) {
        // Attempt database auto-create on localhost MySQL
        if ($mysqlHost === '127.0.0.1' || $mysqlHost === 'localhost') {
            try {
                $rawDsn = "mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4";
                $rawPdo = new PDO($rawDsn, $mysqlUser, $mysqlPass, $options);
                $rawPdo->exec("CREATE DATABASE IF NOT EXISTS `{$mysqlName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                $pdo = new PDO($dsn, $mysqlUser, $mysqlPass, $options);
                return $pdo;
            } catch (PDOException $ex) {
                // Localhost MySQL not running
            }
        }
    }

    // 2. Graceful SQLite Fallback (Runs on Vercel ephemeral storage or when MySQL is unreachable)
    $dbPath = (!empty($_ENV['VERCEL']) || !empty($_SERVER['VERCEL']) || is_dir('/tmp'))
        ? '/tmp/apex_library_v1.sqlite'
        : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'apex_library_v1.sqlite';

    $isNew = !file_exists($dbPath) || filesize($dbPath) === 0;

    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // Compatibility functions for MySQL syntax in SQLite
        $pdo->sqliteCreateFunction('CURDATE', function() {
            return date('Y-m-d');
        });
        $pdo->sqliteCreateFunction('NOW', function() {
            return date('Y-m-d H:i:s');
        });

        if ($isNew) {
            init_sqlite_schema($pdo);
        }

        return $pdo;
    } catch (Throwable $t) {
        die("<div style='font-family:sans-serif;padding:30px;background:#fee2e2;color:#991b1b;border:1px solid #f87171;border-radius:8px;max-width:600px;margin:50px auto;'>
            <h2>Database Connection Error</h2>
            <p>Could not connect to MySQL server at <code>{$mysqlHost}:{$mysqlPort}</code> and SQLite fallback failed.</p>
            <p><strong>Error:</strong> " . htmlspecialchars($t->getMessage()) . "</p>
        </div>");
    }
}

/**
 * Initialize SQLite tables and seed initial data matching schema.sql
 */
function init_sqlite_schema(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fullname TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            email TEXT NOT NULL UNIQUE,
            role TEXT NOT NULL DEFAULT 'students',
            password_hash TEXT NOT NULL,
            salt TEXT NOT NULL,
            otp_code TEXT NULL,
            otp_expires_at TEXT NULL,
            two_factor_enabled INTEGER DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS books (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            author TEXT NOT NULL,
            isbn TEXT UNIQUE NOT NULL,
            category TEXT NOT NULL,
            quantity INTEGER DEFAULT 1,
            available_copies INTEGER DEFAULT 1,
            shelf_location TEXT DEFAULT 'Section A-1',
            description TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS book_loans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            book_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            issued_by TEXT DEFAULT 'Librarian Desk',
            borrow_date TEXT NOT NULL,
            due_date TEXT NOT NULL,
            return_date TEXT NULL,
            status TEXT DEFAULT 'borrowed',
            fine_amount REAL DEFAULT 0.00,
            notes TEXT
        );
        CREATE TABLE IF NOT EXISTS system_configurations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            config_key TEXT UNIQUE NOT NULL,
            config_value TEXT NOT NULL,
            config_group TEXT DEFAULT 'general',
            description TEXT NULL,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS restore_points (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT NULL,
            config_payload TEXT NOT NULL,
            created_by_name TEXT DEFAULT 'Administrator',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS backups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL,
            file_path TEXT NOT NULL,
            file_size INTEGER DEFAULT 0,
            backup_type TEXT DEFAULT 'Full Database SQL',
            created_by TEXT DEFAULT 'admin',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS cloudflare_backup_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            backup_filename TEXT NOT NULL,
            cloudflare_bucket TEXT NOT NULL,
            cloudflare_object_key TEXT NOT NULL,
            cloudflare_etag TEXT NOT NULL,
            cloudflare_ray_id TEXT NOT NULL,
            sync_status TEXT DEFAULT 'synced',
            response_code INTEGER DEFAULT 200,
            cloud_url TEXT NOT NULL,
            synced_at TEXT DEFAULT CURRENT_TIMESTAMP,
            notes TEXT NULL
        );
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NULL,
            role TEXT NULL,
            action TEXT NOT NULL,
            details TEXT NULL,
            ip_address TEXT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Default System Configurations
    $pdo->exec("
        INSERT OR IGNORE INTO system_configurations (config_key, config_value, config_group, description) VALUES
        ('library_name', 'Apex Central Library & Research Hub', 'general', 'Official name of the institution library'),
        ('contact_email', 'library.admin@apexlib.edu', 'general', 'System notification and contact email'),
        ('max_loan_days', '14', 'loans', 'Default maximum days a student may keep a borrowed book'),
        ('fine_per_day', '1.50', 'loans', 'Overdue fine fee per day in USD'),
        ('max_books_per_student', '5', 'loans', 'Maximum books a student can borrow simultaneously'),
        ('otp_expiry_minutes', '10', 'security', 'Number of minutes before Two-Factor OTP code expires'),
        ('salt_entropy_bytes', '16', 'security', 'Cryptographic byte length for user password salt (16 bytes = 32 hex chars)'),
        ('maintenance_mode', '0', 'system', 'Set to 1 to restrict non-admin user access for maintenance'),
        ('cloudflare_r2_bucket', 'apex-library-r2-vault', 'cloudflare', 'Target Cloudflare R2 storage bucket for offsite backups'),
        ('cloudflare_account_id', 'cf_acc_98a72e41c09b882', 'cloudflare', 'Cloudflare Account ID for API synchronization'),
        ('theme_color', '#1e293b', 'display', 'Primary UI theme hex color');
    ");

    // Default Books
    $pdo->exec("
        INSERT OR IGNORE INTO books (title, author, isbn, category, quantity, available_copies, shelf_location, description) VALUES
        ('Introduction to Algorithms (4th Ed)', 'Thomas H. Cormen, Charles E. Leiserson', '978-0262046305', 'Computer Science', 8, 7, 'Floor 2, Shelf CS-101', 'Comprehensive textbook on algorithmic design and computational complexity analysis.'),
        ('Clean Architecture: A Craftsman Guide', 'Robert C. Martin', '978-0134494166', 'Software Engineering', 5, 4, 'Floor 2, Shelf CS-104', 'Universal rules of software architecture and decoupled system design.'),
        ('Modern Operating Systems', 'Andrew S. Tanenbaum', '978-0133591620', 'Computer Science', 6, 6, 'Floor 2, Shelf CS-202', 'Foundational concepts in memory management, concurrency, and virtual file systems.'),
        ('Principles of Neural Science', 'Eric R. Kandel', '978-0071390118', 'Neuroscience', 4, 3, 'Floor 3, Shelf BIO-12', 'Comprehensive reference work in molecular neurobiology and behavioral neuroscience.'),
        ('Artificial Intelligence: A Modern Approach', 'Stuart Russell, Peter Norvig', '978-0134610993', 'Artificial Intelligence', 10, 8, 'Floor 2, Shelf AI-01', 'Authoritative guide covering probabilistic reasoning, deep learning, and planning.'),
        ('The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 'Literature', 7, 7, 'Floor 1, Shelf LIT-09', 'A 1925 novel depicting Jazz Age themes of decadence, idealism, and social upheaval.');
    ");

    // Default Users with Salt & Pepper
    require_once __DIR__ . '/../includes/auth.php';
    $defaultUsers = [
        ['fullname' => 'System Administrator', 'username' => 'admin', 'email' => 'admin@apexlib.local', 'role' => 'admin', 'password' => 'admin123'],
        ['fullname' => 'Sarah Johnson (Student)', 'username' => 'student1', 'email' => 'sarah.j@student.apexlib.local', 'role' => 'students', 'password' => 'student123'],
        ['fullname' => 'David Miller (Head Librarian)', 'username' => 'librarian1', 'email' => 'david.m@librarian.apexlib.local', 'role' => 'libarian', 'password' => 'lib123'],
        ['fullname' => 'Elena Rostova (Books Cataloger)', 'username' => 'bookkeeper1', 'email' => 'elena.r@books.apexlib.local', 'role' => 'books', 'password' => 'books123']
    ];

    $uStmt = $pdo->prepare("INSERT OR IGNORE INTO users (fullname, username, email, role, password_hash, salt, two_factor_enabled) VALUES (?, ?, ?, ?, ?, ?, 1)");
    foreach ($defaultUsers as $u) {
        $salt = generate_user_salt(16);
        $hash = hash_password_salt_pepper($u['password'], $salt);
        $uStmt->execute([$u['fullname'], $u['username'], $u['email'], $u['role'], $hash, $salt]);
    }
}
