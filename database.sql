-- =============================================
-- اسکریپت SQL ربات فروشگاه فایل تلگرام
-- =============================================

CREATE DATABASE IF NOT EXISTS `telegram_shop`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `telegram_shop`;

-- جدول کاربران
CREATE TABLE IF NOT EXISTS `users` (
    `id`          BIGINT NOT NULL PRIMARY KEY COMMENT 'آیدی تلگرام کاربر',
    `username`    VARCHAR(100) DEFAULT NULL,
    `first_name`  VARCHAR(100) NOT NULL DEFAULT '',
    `last_name`   VARCHAR(100) DEFAULT NULL,
    `wallet`      BIGINT NOT NULL DEFAULT 0 COMMENT 'موجودی کیف پول (تومان)',
    `state`       INT NOT NULL DEFAULT 0 COMMENT 'وضعیت مکالمه',
    `state_data`  TEXT DEFAULT NULL COMMENT 'داده‌های وضعیت (JSON)',
    `is_banned`   TINYINT(1) NOT NULL DEFAULT 0,
    `joined_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول فایل‌ها
CREATE TABLE IF NOT EXISTS `files` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price`       BIGINT NOT NULL DEFAULT 0 COMMENT 'قیمت به تومان',
    `file_id`     VARCHAR(255) NOT NULL COMMENT 'file_id تلگرام',
    `file_type`   VARCHAR(50) DEFAULT 'document',
    `file_size`   BIGINT DEFAULT 0 COMMENT 'حجم فایل به بایت',
    `seller_id`   BIGINT DEFAULT NULL COMMENT 'آیدی ادمین آپلودکننده',
    `downloads`   INT NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول خریدها
CREATE TABLE IF NOT EXISTS `purchases` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`      BIGINT NOT NULL,
    `file_id`      INT NOT NULL,
    `amount`       BIGINT NOT NULL COMMENT 'مبلغ پرداخت شده',
    `purchased_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_file` (`user_id`, `file_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`file_id`) REFERENCES `files`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول تراکنش‌ها
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     BIGINT NOT NULL,
    `amount`      BIGINT NOT NULL,
    `type`        ENUM('charge','purchase','refund') NOT NULL,
    `status`      ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    `authority`   VARCHAR(100) DEFAULT NULL COMMENT 'کد زرین‌پال',
    `ref_id`      VARCHAR(100) DEFAULT NULL COMMENT 'کد پیگیری',
    `description` TEXT DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_authority` (`authority`),
    INDEX `idx_user`      (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- جدول تنظیمات
CREATE TABLE IF NOT EXISTS `settings` (
    `key_name` VARCHAR(100) NOT NULL PRIMARY KEY,
    `value`    TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- مقادیر پیش‌فرض تنظیمات
INSERT IGNORE INTO `settings` (`key_name`, `value`) VALUES
('zarinpal_merchant',   ''),
('zarinpal_sandbox',    '0'),
('zarinpal_active',     '0'),
('shop_name',           'فروشگاه فایل'),
('shop_description',    'خرید و فروش فایل‌های دیجیتال'),
('bot_active',          '1');
