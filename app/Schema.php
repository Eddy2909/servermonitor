<?php
declare(strict_types=1);

namespace ModernMonitor;

use RuntimeException;

final class Schema
{
    public static function ensure(Database $db): void
    {
        foreach (self::statements($db->prefix()) as $statement) {
            $db->pdo()->exec($statement);
        }

        self::modifyServerType($db);
        self::addColumn($db, 'servers', 'public_visible', '`public_visible` TINYINT(1) NOT NULL DEFAULT 1 AFTER `enabled`');
        self::addColumn($db, 'servers', 'notify_enabled', '`notify_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `public_visible`');
        self::addColumn($db, 'servers', 'notify_email', '`notify_email` VARCHAR(190) NULL AFTER `notify_enabled`');
        self::addColumn($db, 'servers', 'notify_on_down', '`notify_on_down` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_email`');
        self::addColumn($db, 'servers', 'notify_on_recovery', '`notify_on_recovery` TINYINT(1) NOT NULL DEFAULT 1 AFTER `notify_on_down`');
        self::addColumn($db, 'servers', 'last_notified_status', "`last_notified_status` ENUM('unknown','up','down') NOT NULL DEFAULT 'unknown' AFTER `last_checked_at`");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix()}notifications` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix()}public_pages` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$db->prefix()}public_page_servers` (
            `page_id` INT UNSIGNED NOT NULL,
            `server_id` INT UNSIGNED NOT NULL,
            `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`page_id`, `server_id`),
            KEY `server_id` (`server_id`),
            CONSTRAINT `{$db->prefix()}public_page_servers_page_fk` FOREIGN KEY (`page_id`) REFERENCES `{$db->prefix()}public_pages` (`id`) ON DELETE CASCADE,
            CONSTRAINT `{$db->prefix()}public_page_servers_server_fk` FOREIGN KEY (`server_id`) REFERENCES `{$db->prefix()}servers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::seedDefaultPublicPage($db);
    }

    public static function statements(string $prefix): array
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            throw new RuntimeException('Invalid table prefix.');
        }

        return [
            "CREATE TABLE IF NOT EXISTS `{$prefix}users` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(80) NOT NULL,
                `email` VARCHAR(190) NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `role` VARCHAR(40) NOT NULL DEFAULT 'admin',
                `created_at` DATETIME NOT NULL,
                `last_login_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}servers` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}checks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `server_id` INT UNSIGNED NOT NULL,
                `status` ENUM('up','down') NOT NULL,
                `response_time_ms` INT UNSIGNED NULL,
                `http_code` INT UNSIGNED NULL,
                `error_message` VARCHAR(500) NULL,
                `checked_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `server_checked` (`server_id`, `checked_at`),
                CONSTRAINT `{$prefix}checks_server_fk` FOREIGN KEY (`server_id`) REFERENCES `{$prefix}servers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
                `name` VARCHAR(120) NOT NULL,
                `value` VARCHAR(500) NOT NULL,
                PRIMARY KEY (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}notifications` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}public_pages` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `{$prefix}public_page_servers` (
                `page_id` INT UNSIGNED NOT NULL,
                `server_id` INT UNSIGNED NOT NULL,
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`page_id`, `server_id`),
                KEY `server_id` (`server_id`),
                CONSTRAINT `{$prefix}public_page_servers_page_fk` FOREIGN KEY (`page_id`) REFERENCES `{$prefix}public_pages` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$prefix}public_page_servers_server_fk` FOREIGN KEY (`server_id`) REFERENCES `{$prefix}servers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    private static function seedDefaultPublicPage(Database $db): void
    {
        $row = $db->fetchOne('SELECT COUNT(*) AS total FROM ' . $db->table('public_pages'));
        if ((int)($row['total'] ?? 0) > 0) {
            return;
        }

        $settings = [];
        foreach ($db->fetchAll('SELECT name, value FROM ' . $db->table('settings')) as $setting) {
            $settings[(string)$setting['name']] = (string)$setting['value'];
        }

        $enabled = ($settings['public_status_enabled'] ?? '1') === '1' ? 1 : 0;
        $theme = ($settings['public_status_theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
        $accent = (string)($settings['public_status_accent'] ?? '#5dd6a5');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#5dd6a5';
        }

        $db->execute(
            'INSERT INTO ' . $db->table('public_pages') . '
             (token, title, badge, description, theme, accent, enabled, show_latency, show_uptime,
              show_last_check, show_incidents, footer_note, created_at, updated_at)
             VALUES (:token, :title, :badge, :description, :theme, :accent, :enabled, :show_latency,
              :show_uptime, :show_last_check, :show_incidents, :footer_note, NOW(), NOW())',
            [
                'token' => bin2hex(random_bytes(18)),
                'title' => (string)($settings['public_status_title'] ?? 'System Status'),
                'badge' => (string)($settings['public_status_badge'] ?? 'Live infrastructure status'),
                'description' => (string)($settings['public_status_description'] ?? 'Live status of monitored services.'),
                'theme' => $theme,
                'accent' => $accent,
                'enabled' => $enabled,
                'show_latency' => ($settings['public_show_latency'] ?? '1') === '1' ? 1 : 0,
                'show_uptime' => ($settings['public_show_uptime'] ?? '1') === '1' ? 1 : 0,
                'show_last_check' => ($settings['public_show_last_check'] ?? '1') === '1' ? 1 : 0,
                'show_incidents' => ($settings['public_show_incidents'] ?? '1') === '1' ? 1 : 0,
                'footer_note' => (string)($settings['public_footer_note'] ?? ''),
            ]
        );

        $pageId = $db->lastInsertId();
        $servers = $db->fetchAll('SELECT id FROM ' . $db->table('servers') . ' WHERE public_visible = 1 ORDER BY name ASC');
        foreach ($servers as $index => $server) {
            $db->execute(
                'INSERT INTO ' . $db->table('public_page_servers') . ' (page_id, server_id, sort_order)
                 VALUES (:page_id, :server_id, :sort_order)',
                ['page_id' => $pageId, 'server_id' => (int)$server['id'], 'sort_order' => $index]
            );
        }
    }

    private static function addColumn(Database $db, string $table, string $column, string $definition): void
    {
        $row = $db->fetchOne(
            'SELECT COUNT(*) AS total FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
            ['table' => $db->prefix() . $table, 'column' => $column]
        );

        if ((int)($row['total'] ?? 0) === 0) {
            $db->pdo()->exec('ALTER TABLE `' . $db->prefix() . $table . '` ADD COLUMN ' . $definition);
        }
    }

    private static function modifyServerType(Database $db): void
    {
        $row = $db->fetchOne(
            'SELECT COLUMN_TYPE AS column_type FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = "type"',
            ['table' => $db->prefix() . 'servers']
        );

        if ($row !== null && strpos((string)$row['column_type'], 'ping') === false) {
            $db->pdo()->exec("ALTER TABLE `{$db->prefix()}servers` MODIFY COLUMN `type` ENUM('website','tcp','ping') NOT NULL DEFAULT 'website'");
        }
    }
}
