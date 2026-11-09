# ApexLib - Enterprise Library Management System

A secured, multi-role Library Management System built for XAMPP (PHP 8+ & MariaDB/MySQL).

## 🚀 Key Features

### 1. Cryptographic Salt & Pepper Architecture
- **Per-User Salt**: A unique random salt is stored for each user in the database and is never displayed in the application.
- **Server-Side Pepper**: A global master secret key (`SYSTEM_PEPPER`) defined strictly in server configuration (`config/config.php`), never written to the database.
- **Hashing Pipeline**: 
  `Password + User Salt -> HMAC-SHA256(System Pepper) -> BCrypt Hash (Cost 11)`
  *Even if the entire database is leaked, passwords cannot be cracked without the server Pepper.*

### 2. Four Distinct User Roles
Users select their role when logging in, registering, or resetting passwords:
1. `admin` (**Administrator**): Executive management dashboard, user management, backups & Cloudflare vault, non-destructive restore points, system configurations, and audit trail.
2. `students` (**Student**): Self-service catalog browsing, one-click borrow requests, active loan countdowns, overdue warnings, and borrowing history.
3. `libarian` (**Librarian**): Circulation desk for issuing books, processing check-ins, automatic overdue fine calculations, and loan registers.
4. `books` (**Books Manager**): Catalog specialist for physical stock counts, ISBN tracking, category organization, and shelf locations.

### 3. Non-Destructive Configuration Restore Points
- **Rule**: *"The system should get a restore point and it should not change the data but only the configuration."*
- Configuration Restore Points capture JSON snapshots of `system_configurations` (loan durations, daily overdue rates, OTP expiry, maintenance mode, etc.).
- When restored, **only** the configuration parameters are rolled back. **Zero books, loans, or user records are ever modified or erased.**

### 4. Database Backups & Cloudflare Records Vault
- Generates full `.sql` dumps in the `backups/` directory.
- Automatically archives backup metadata to Cloudflare R2 object storage records (`cloudflare_backup_records`), tracking Cloudflare Ray IDs, ETags, R2 Bucket names, and 200 OK sync status.

### 5. Two-Step Verification (6-Digit OTP)
- Professional 6-segment digit input with auto-advance and clipboard paste support.
- Generated upon login and password reset.
- Includes a localhost demo banner displaying the active code for instant evaluation in local environments.

---

## 🔑 Local Test Accounts

| Role | Username | Password | Role Value |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | `admin` |
| **Student** | `student1` | `student123` | `students` |
| **Librarian** | `librarian1` | `lib123` | `libarian` |
| **Books Manager** | `bookkeeper1` | `books123` | `books` |

These seeded accounts are for local evaluation only. Change or remove their passwords and update their email addresses before moving a database to a public host. Public registration creates student accounts only; privileged roles must be assigned by an administrator.

---

## 🌐 Local Access URLs

- **Portal URL**: [http://localhost/libary/](http://localhost/libary/)
- **Sign In**: [http://localhost/libary/login.php](http://localhost/libary/login.php)
- **Create Account**: [http://localhost/libary/register.php](http://localhost/libary/register.php)
- **Forgot Password**: [http://localhost/libary/forgot_password.php](http://localhost/libary/forgot_password.php)

## Public Hosting Notes

- Install Composer dependencies with `composer install --no-dev` before uploading.
- Keep database and SMTP settings in `config/local.php`; do not publish that file or share it.
- Define `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`, plus `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USERNAME`, `SMTP_PASSWORD`, and `SMTP_FROM_EMAIL` in that file.
- Configure SMTP with an app-specific password so login and password-reset OTPs can be delivered.
- The root `.htaccess` blocks direct access to configuration, installer, backup, and test paths. Do not upload `database/setup.php` to a live site; it resets tables.
- Do not use `database/schema.sql` to migrate existing data because it drops and recreates tables. Export the current database and import that dump into a newly created hosting database.
