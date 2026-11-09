-- ============================================================
-- APEXLIB SYSTEM BACKUP DUMP
-- Generated: 2026-10-16 15:48:43
-- Server Version: 10.4.32-MariaDB
-- Database: library_db
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- Table structure for `system_configurations`
DROP TABLE IF EXISTS `system_configurations`;
CREATE TABLE `system_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(80) NOT NULL,
  `config_value` text NOT NULL,
  `config_group` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `system_configurations`
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('1', 'library_name', 'Apex Central Library & Research Hub', 'general', 'Official name of the institution library', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('2', 'contact_email', 'library.admin@apexlib.edu', 'general', 'System notification and contact email', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('3', 'max_loan_days', '14', 'loans', 'Default maximum days a student may keep a borrowed book', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('4', 'fine_per_day', '1.50', 'loans', 'Overdue fine fee per day in USD', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('5', 'max_books_per_student', '5', 'loans', 'Maximum books a student can borrow simultaneously', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('6', 'otp_expiry_minutes', '10', 'security', 'Number of minutes before Two-Factor OTP code expires', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('7', 'salt_entropy_bytes', '16', 'security', 'Cryptographic byte length for user password salt (16 bytes = 32 hex chars)', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('8', 'maintenance_mode', '0', 'system', 'Set to 1 to restrict non-admin user access for maintenance', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('9', 'cloudflare_r2_bucket', 'apex-library-r2-vault', 'cloudflare', 'Target Cloudflare R2 storage bucket for offsite backups', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('10', 'cloudflare_account_id', 'cf_acc_98a72e41c09b882', 'cloudflare', 'Cloudflare Account ID for API synchronization', '2026-10-16 06:42:23');
INSERT INTO `system_configurations` (`id`, `config_key`, `config_value`, `config_group`, `description`, `updated_at`) VALUES ('11', 'theme_color', '#1e293b', 'display', 'Primary UI theme hex color', '2026-10-16 06:42:23');

-- Table structure for `restore_points`
DROP TABLE IF EXISTS `restore_points`;
CREATE TABLE `restore_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `config_payload` longtext NOT NULL COMMENT 'JSON snapshot of system_configurations ONLY',
  `created_by_name` varchar(100) DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `restore_points`
INSERT INTO `restore_points` (`id`, `name`, `description`, `config_payload`, `created_by_name`, `created_at`) VALUES ('1', 'Baseline Academic Policy (Fall 2026)', 'Standard university borrowing limits: 14-day loan window, $1.50/day overdue fine, 10-minute OTP expiration.', '{\n    \"metadata\": {\n        \"created_at\": \"2026-10-16 15:42:24\",\n        \"app_version\": \"2.5.0-Enterprise\",\n        \"scope\": \"configuration_only\",\n        \"created_by\": \"System Administrator\"\n    },\n    \"configurations\": [\n        {\n            \"config_key\": \"library_name\",\n            \"config_value\": \"Apex Central Library & Research Hub\",\n            \"config_group\": \"general\",\n            \"description\": \"Official name of the institution library\"\n        },\n        {\n            \"config_key\": \"contact_email\",\n            \"config_value\": \"library.admin@apexlib.edu\",\n            \"config_group\": \"general\",\n            \"description\": \"System notification and contact email\"\n        },\n        {\n            \"config_key\": \"max_loan_days\",\n            \"config_value\": \"14\",\n            \"config_group\": \"loans\",\n            \"description\": \"Default maximum days a student may keep a borrowed book\"\n        },\n        {\n            \"config_key\": \"fine_per_day\",\n            \"config_value\": \"1.50\",\n            \"config_group\": \"loans\",\n            \"description\": \"Overdue fine fee per day in USD\"\n        },\n        {\n            \"config_key\": \"max_books_per_student\",\n            \"config_value\": \"5\",\n            \"config_group\": \"loans\",\n            \"description\": \"Maximum books a student can borrow simultaneously\"\n        },\n        {\n            \"config_key\": \"otp_expiry_minutes\",\n            \"config_value\": \"10\",\n            \"config_group\": \"security\",\n            \"description\": \"Number of minutes before Two-Factor OTP code expires\"\n        },\n        {\n            \"config_key\": \"salt_entropy_bytes\",\n            \"config_value\": \"16\",\n            \"config_group\": \"security\",\n            \"description\": \"Cryptographic byte length for user password salt (16 bytes = 32 hex chars)\"\n        },\n        {\n            \"config_key\": \"maintenance_mode\",\n            \"config_value\": \"0\",\n            \"config_group\": \"system\",\n            \"description\": \"Set to 1 to restrict non-admin user access for maintenance\"\n        },\n        {\n            \"config_key\": \"cloudflare_r2_bucket\",\n            \"config_value\": \"apex-library-r2-vault\",\n            \"config_group\": \"cloudflare\",\n            \"description\": \"Target Cloudflare R2 storage bucket for offsite backups\"\n        },\n        {\n            \"config_key\": \"cloudflare_account_id\",\n            \"config_value\": \"cf_acc_98a72e41c09b882\",\n            \"config_group\": \"cloudflare\",\n            \"description\": \"Cloudflare Account ID for API synchronization\"\n        },\n        {\n            \"config_key\": \"theme_color\",\n            \"config_value\": \"#1e293b\",\n            \"config_group\": \"display\",\n            \"description\": \"Primary UI theme hex color\"\n        }\n    ]\n}', 'System Administrator', '2026-10-16 06:42:24');

-- Table structure for `users`
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(60) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','students','libarian','books') NOT NULL DEFAULT 'students',
  `password_hash` varchar(255) NOT NULL,
  `salt` varchar(64) NOT NULL COMMENT 'Unique cryptographically generated random salt per user',
  `otp_code` varchar(10) DEFAULT NULL COMMENT 'Current active 6-digit OTP code',
  `otp_expires_at` datetime DEFAULT NULL COMMENT 'Expiration timestamp for OTP',
  `two_factor_enabled` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `users`
INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `role`, `password_hash`, `salt`, `otp_code`, `otp_expires_at`, `two_factor_enabled`, `created_at`, `updated_at`) VALUES ('1', 'System Administrator', 'admin', 'admin@apexlib.local', 'admin', '$2y$11$p00OL4BbwpmYZkQlDb1g1O8McSYz8KXedz8Pq1Zbkfa.D9Kid6/ES', 'c7a463a78fc892f5b65c2b9a47d6040e', NULL, NULL, '1', '2026-10-16 06:42:23', '2026-10-16 06:48:43');
INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `role`, `password_hash`, `salt`, `otp_code`, `otp_expires_at`, `two_factor_enabled`, `created_at`, `updated_at`) VALUES ('2', 'Sarah Johnson (Student)', 'student1', 'sarah.j@student.apexlib.local', 'students', '$2y$11$ulMfqPu1NJeNdQ1Wtv9.UO.uEovMphIWJm/NPSGBtEjQDGptjSteO', '84891e8e6e90aee75d0ca0a1aaa4e686', NULL, NULL, '1', '2026-10-16 06:42:23', '2026-10-16 06:42:23');
INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `role`, `password_hash`, `salt`, `otp_code`, `otp_expires_at`, `two_factor_enabled`, `created_at`, `updated_at`) VALUES ('3', 'David Miller (Head Librarian)', 'librarian1', 'david.m@librarian.apexlib.local', 'libarian', '$2y$11$QdvFcvngQ8xIygkrDC5qkuGzCa5b8Xn6oywMH8zM98LUjP7PtDOOu', 'fc013e4aca6a97421e21cf30f0343fb5', NULL, NULL, '1', '2026-10-16 06:42:24', '2026-10-16 06:42:24');
INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `role`, `password_hash`, `salt`, `otp_code`, `otp_expires_at`, `two_factor_enabled`, `created_at`, `updated_at`) VALUES ('4', 'Elena Rostova (Books Cataloger)', 'bookkeeper1', 'elena.r@books.apexlib.local', 'books', '$2y$11$5C7NbayrIFtQdPHQkpB76e32xsivHVK/xn4K9eXufXT.oxBer5NRa', '837507e8fabdb8e3170535735d2196f8', NULL, NULL, '1', '2026-10-16 06:42:24', '2026-10-16 06:42:24');

-- Table structure for `books`
DROP TABLE IF EXISTS `books`;
CREATE TABLE `books` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `author` varchar(255) NOT NULL,
  `isbn` varchar(30) NOT NULL,
  `category` varchar(100) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `available_copies` int(11) DEFAULT 1,
  `shelf_location` varchar(50) DEFAULT 'Section A-1',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `isbn` (`isbn`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `books`
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES ('1', 'Introduction to Algorithms (4th Ed)', 'Thomas H. Cormen, Charles E. Leiserson', '978-0262046305', 'Computer Science', '8', '7', 'Floor 2, Shelf CS-101', 'Comprehensive textbook on algorithmic design and computational complexity analysis.', '2026-10-16 06:42:23');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES ('2', 'Clean Architecture: A Craftsman\'s Guide', 'Robert C. Martin', '978-0134494166', 'Software Engineering', '5', '4', 'Floor 2, Shelf CS-104', 'Universal rules of software architecture and decoupled system design.', '2026-10-16 06:42:23');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES ('3', 'Modern Operating Systems', 'Andrew S. Tanenbaum', '978-0133591620', 'Computer Science', '6', '6', 'Floor 2, Shelf CS-202', 'Foundational concepts in memory management, concurrency, and virtual file systems.', '2026-10-16 06:42:23');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES ('4', 'Principles of Neural Science', 'Eric R. Kandel', '978-0071390118', 'Neuroscience', '4', '3', 'Floor 3, Shelf BIO-12', 'Comprehensive reference work in molecular neurobiology and behavioral neuroscience.', '2026-10-16 06:42:23');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES ('5', 'Artificial Intelligence: A Modern Approach', 'Stuart Russell, Peter Norvig', '978-0134610993', 'Artificial Intelligence', '10', '8', 'Floor 2, Shelf AI-01', 'Authoritative guide covering probabilistic reasoning, deep learning, and planning.', '2026-10-16 06:42:23');
INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `category`, `quantity`, `available_copies`, `shelf_location`, `description`, `created_at`) VALUES ('6', 'The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 'Literature', '7', '7', 'Floor 1, Shelf LIT-09', 'A 1925 novel depicting Jazz Age themes of decadence, idealism, and social upheaval.', '2026-10-16 06:42:23');

-- Table structure for `book_loans`
DROP TABLE IF EXISTS `book_loans`;
CREATE TABLE `book_loans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `issued_by` varchar(100) DEFAULT 'Librarian Desk',
  `borrow_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('borrowed','returned','overdue') DEFAULT 'borrowed',
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `book_id` (`book_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `book_loans_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  CONSTRAINT `book_loans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `book_loans`
INSERT INTO `book_loans` (`id`, `book_id`, `user_id`, `issued_by`, `borrow_date`, `due_date`, `return_date`, `status`, `fine_amount`, `notes`) VALUES ('1', '4', '2', 'David Miller (Librarian)', '2026-10-11', '2026-10-25', NULL, 'borrowed', '0.00', NULL);
INSERT INTO `book_loans` (`id`, `book_id`, `user_id`, `issued_by`, `borrow_date`, `due_date`, `return_date`, `status`, `fine_amount`, `notes`) VALUES ('2', '3', '2', 'David Miller (Librarian)', '2026-09-26', '2026-10-10', NULL, 'overdue', '9.00', 'Overdue by 6 days at $1.50/day');

-- Table structure for `backups`
DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `backup_type` varchar(50) DEFAULT 'Full Database SQL',
  `created_by` varchar(100) DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `backups`
INSERT INTO `backups` (`id`, `filename`, `file_path`, `file_size`, `backup_type`, `created_by`, `created_at`) VALUES ('1', 'backup_library_db_2026-10-16_154224.sql', 'C:\\xampp\\htdocs\\libary\\config/../backups/backup_library_db_2026-10-16_154224.sql', '16825', 'Full SQL Dump', 'System Administrator', '2026-10-16 06:42:24');

-- Table structure for `cloudflare_backup_records`
DROP TABLE IF EXISTS `cloudflare_backup_records`;
CREATE TABLE `cloudflare_backup_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_filename` varchar(255) NOT NULL,
  `cloudflare_bucket` varchar(100) NOT NULL,
  `cloudflare_object_key` varchar(255) NOT NULL,
  `cloudflare_etag` varchar(100) NOT NULL,
  `cloudflare_ray_id` varchar(100) NOT NULL,
  `sync_status` enum('synced','pending','failed') DEFAULT 'synced',
  `response_code` int(11) DEFAULT 200,
  `cloud_url` varchar(255) NOT NULL,
  `synced_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `cloudflare_backup_records`
INSERT INTO `cloudflare_backup_records` (`id`, `backup_filename`, `cloudflare_bucket`, `cloudflare_object_key`, `cloudflare_etag`, `cloudflare_ray_id`, `sync_status`, `response_code`, `cloud_url`, `synced_at`, `notes`) VALUES ('1', 'backup_library_db_2026-10-16_154224.sql', 'apex-library-r2-vault', 'backups/2026/10/backup_library_db_2026-10-16_154224.sql', '\"d2aab1be4e515499dcd26ebdcbc3b59a\"', 'cf-ray-da384f9dc49c6c0b', 'synced', '200', 'https://cf_acc_98a72e41c09b882.r2.cloudflarestorage.com/apex-library-r2-vault/backups/2026/10/backup_library_db_2026-10-16_154224.sql', '2026-10-16 06:42:24', 'Automated cryptographic digest verified. Synced to Cloudflare R2 Object Storage Vault via Cloudflare API v4.');

-- Table structure for `audit_logs`
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(60) DEFAULT NULL,
  `role` varchar(30) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data for `audit_logs`
INSERT INTO `audit_logs` (`id`, `username`, `role`, `action`, `details`, `ip_address`, `created_at`) VALUES ('1', 'System Administrator', 'admin', 'RESTORE_POINT_CREATED', 'Created configuration restore point #1: \'Baseline Academic Policy (Fall 2026)\'', '127.0.0.1', '2026-10-16 06:42:24');
INSERT INTO `audit_logs` (`id`, `username`, `role`, `action`, `details`, `ip_address`, `created_at`) VALUES ('2', 'System Administrator', 'admin', 'BACKUP_CREATED_AND_SYNCED', 'Created backup \'backup_library_db_2026-10-16_154224.sql\' (16825 bytes) and recorded Cloudflare R2 archive [RayID: cf-ray-da384f9dc49c6c0b]', '127.0.0.1', '2026-10-16 06:42:24');

SET FOREIGN_KEY_CHECKS=1;
