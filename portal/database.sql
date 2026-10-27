-- MitID Database Schema for MySQL / MariaDB (InfinityFree / LAMP)
-- Import this file into phpMyAdmin or run it in your MySQL console.

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for `sessions`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sessions` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `auth_log`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `auth_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `detail` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_session` (`session_id`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_auth_log_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `admins`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `ws_messages` (Real-time polling queue for InfinityFree)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ws_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `session_id` VARCHAR(64) NOT NULL,
  `sender_role` VARCHAR(16) NOT NULL,
  `target_role` VARCHAR(16) NOT NULL,
  `message` LONGTEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_poll` (`session_id`, `target_role`, `id`),
  INDEX `idx_ws_created` (`created_at`),
  CONSTRAINT `fk_ws_messages_session` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Seed default admin account
-- Default email: admin@admin
-- Default password: Admin123@@
-- --------------------------------------------------------

INSERT INTO `admins` (`email`, `password_hash`, `created_at`)
VALUES ('admin@admin', '$2y$10$LywaP1dG9O1XkfsgJ.lJx.5JOLVt26Fbtxhz71hQ/yQNO2.iCMGWe', NOW())
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`), `password_hash` = VALUES(`password_hash`);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;
