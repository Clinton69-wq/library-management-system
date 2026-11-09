-- Library Management System Database Schema
-- Supports Salt & Pepper, 4 Roles ('admin', 'students', 'libarian', 'books'),
-- Configuration-only Restore Points, Backups, and Cloudflare Backup Records.

CREATE DATABASE IF NOT EXISTS `library_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `library_db`;

-- Drop existing tables if needed for a clean installation
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `cloudflare_backup_records`;
DROP TABLE IF EXISTS `backups`;
DROP TABLE IF EXISTS `restore_points`;
DROP TABLE IF EXISTS `system_configurations`;
DROP TABLE IF EXISTS `book_loans`;
DROP TABLE IF EXISTS `books`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table (Stores generated unique SALT per user, role, and OTP details)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `fullname` VARCHAR(100) NOT NULL,
  `username` VARCHAR(60) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `role` ENUM('admin', 'students', 'libarian', 'books') NOT NULL DEFAULT 'students',
  `password_hash` VARCHAR(255) NOT NULL,
  `salt` VARCHAR(64) NOT NULL COMMENT 'Unique cryptographically generated random salt per user',
  `otp_code` VARCHAR(10) NULL COMMENT 'Current active 6-digit OTP code',
  `otp_expires_at` DATETIME NULL COMMENT 'Expiration timestamp for OTP',
  `two_factor_enabled` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Books Catalog & Inventory Table
CREATE TABLE `books` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `isbn` VARCHAR(30) UNIQUE NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `quantity` INT DEFAULT 1,
  `available_copies` INT DEFAULT 1,
  `shelf_location` VARCHAR(50) DEFAULT 'Section A-1',
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Book Loans / Borrow Records
CREATE TABLE `book_loans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `book_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `issued_by` VARCHAR(100) DEFAULT 'Librarian Desk',
  `borrow_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `return_date` DATE NULL,
  `status` ENUM('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
  `fine_amount` DECIMAL(10,2) DEFAULT 0.00,
  `notes` TEXT,
  FOREIGN KEY (`book_id`) REFERENCES `books`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. System Configurations Table (Restored independently via Restore Points!)
CREATE TABLE `system_configurations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `config_key` VARCHAR(80) UNIQUE NOT NULL,
  `config_value` TEXT NOT NULL,
  `config_group` VARCHAR(50) DEFAULT 'general',
  `description` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Configuration-only Restore Points
CREATE TABLE `restore_points` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `config_payload` LONGTEXT NOT NULL COMMENT 'JSON snapshot of system_configurations ONLY',
  `created_by_name` VARCHAR(100) DEFAULT 'Administrator',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Local Database Backups
CREATE TABLE `backups` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` BIGINT DEFAULT 0,
  `backup_type` VARCHAR(50) DEFAULT 'Full Database SQL',
  `created_by` VARCHAR(100) DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Cloudflare Backup Records (Tracks backups archived to Cloudflare R2 / Object Storage)
CREATE TABLE `cloudflare_backup_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `backup_filename` VARCHAR(255) NOT NULL,
  `cloudflare_bucket` VARCHAR(100) NOT NULL,
  `cloudflare_object_key` VARCHAR(255) NOT NULL,
  `cloudflare_etag` VARCHAR(100) NOT NULL,
  `cloudflare_ray_id` VARCHAR(100) NOT NULL,
  `sync_status` ENUM('synced', 'pending', 'failed') DEFAULT 'synced',
  `response_code` INT DEFAULT 200,
  `cloud_url` VARCHAR(255) NOT NULL,
  `synced_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Audit Logs
CREATE TABLE `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(60) NULL,
  `role` VARCHAR(30) NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Configurations
INSERT INTO `system_configurations` (`config_key`, `config_value`, `config_group`, `description`) VALUES
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

-- Seed Initial Books
INSERT INTO `books` (`title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`) VALUES
('Introduction to Algorithms (4th Ed)', 'Thomas H. Cormen, Charles E. Leiserson', '978-0262046305', 'Computer Science', 8, 7, 'Floor 2, Shelf CS-101', 'Comprehensive textbook on algorithmic design and computational complexity analysis.'),
('Clean Architecture: A Craftsman''s Guide', 'Robert C. Martin', '978-0134494166', 'Software Engineering', 5, 4, 'Floor 2, Shelf CS-104', 'Universal rules of software architecture and decoupled system design.'),
('Modern Operating Systems', 'Andrew S. Tanenbaum', '978-0133591620', 'Computer Science', 6, 6, 'Floor 2, Shelf CS-202', 'Foundational concepts in memory management, concurrency, and virtual file systems.'),
('Principles of Neural Science', 'Eric R. Kandel', '978-0071390118', 'Neuroscience', 4, 3, 'Floor 3, Shelf BIO-12', 'Comprehensive reference work in molecular neurobiology and behavioral neuroscience.'),
('Artificial Intelligence: A Modern Approach', 'Stuart Russell, Peter Norvig', '978-0134610993', 'Artificial Intelligence', 10, 8, 'Floor 2, Shelf AI-01', 'Authoritative guide covering probabilistic reasoning, deep learning, and planning.'),
('The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 'Literature', 7, 7, 'Floor 1, Shelf LIT-09', 'A 1925 novel depicting Jazz Age themes of decadence, idealism, and social upheaval.');
