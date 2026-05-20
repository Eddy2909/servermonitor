CREATE TABLE IF NOT EXISTS `monitor_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(80) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(40) NOT NULL DEFAULT 'admin',
    `created_at` DATETIME NOT NULL,
    `last_login_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitor_servers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(190) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `type` ENUM('website','tcp','ping') NOT NULL DEFAULT 'website',
    `port` INT UNSIGNED NULL,
    `method` ENUM('GET','HEAD') NOT NULL DEFAULT 'GET',
    `expected_status` VARCHAR(80) NOT NULL DEFAULT '200-399',
    `expected_text` VARCHAR(255) NOT NULL DEFAULT '',
    `timeout_seconds` TINYINT UNSIGNED NOT NULL DEFAULT 10,
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `public_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `notify_email` VARCHAR(190) NULL,
    `notify_on_down` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_on_recovery` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('unknown','up','down') NOT NULL DEFAULT 'unknown',
    `response_time_ms` INT UNSIGNED NULL,
    `http_code` INT UNSIGNED NULL,
    `uptime_score` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `last_error` VARCHAR(500) NULL,
    `last_checked_at` DATETIME NULL,
    `last_notified_status` ENUM('unknown','up','down') NOT NULL DEFAULT 'unknown',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `status_enabled` (`status`, `enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitor_checks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `server_id` INT UNSIGNED NOT NULL,
    `status` ENUM('up','down') NOT NULL,
    `response_time_ms` INT UNSIGNED NULL,
    `http_code` INT UNSIGNED NULL,
    `error_message` VARCHAR(500) NULL,
    `checked_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `server_checked` (`server_id`, `checked_at`),
    CONSTRAINT `monitor_checks_server_fk` FOREIGN KEY (`server_id`) REFERENCES `monitor_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitor_settings` (
    `name` VARCHAR(120) NOT NULL,
    `value` VARCHAR(500) NOT NULL,
    PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitor_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `server_id` INT UNSIGNED NULL,
    `recipient` VARCHAR(190) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `status` ENUM('sent','failed') NOT NULL,
    `error_message` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `server_created` (`server_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitor_public_pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `token` VARCHAR(80) NOT NULL,
    `title` VARCHAR(190) NOT NULL,
    `badge` VARCHAR(190) NOT NULL DEFAULT '',
    `description` VARCHAR(500) NOT NULL DEFAULT '',
    `theme` ENUM('dark','light') NOT NULL DEFAULT 'dark',
    `accent` CHAR(7) NOT NULL DEFAULT '#5dd6a5',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `show_latency` TINYINT(1) NOT NULL DEFAULT 1,
    `show_uptime` TINYINT(1) NOT NULL DEFAULT 1,
    `show_last_check` TINYINT(1) NOT NULL DEFAULT 1,
    `show_incidents` TINYINT(1) NOT NULL DEFAULT 1,
    `footer_note` VARCHAR(500) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitor_public_page_servers` (
    `page_id` INT UNSIGNED NOT NULL,
    `server_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`page_id`, `server_id`),
    KEY `server_id` (`server_id`),
    CONSTRAINT `monitor_public_page_servers_page_fk` FOREIGN KEY (`page_id`) REFERENCES `monitor_public_pages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `monitor_public_page_servers_server_fk` FOREIGN KEY (`server_id`) REFERENCES `monitor_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
