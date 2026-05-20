<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ModernMonitor\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = APP_DIR . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function app_config_path(): string
{
    return APP_ROOT . DIRECTORY_SEPARATOR . 'config.php';
}

function load_config(bool $required = true): array
{
    $path = app_config_path();
    if (!is_file($path)) {
        if ($required) {
            throw new RuntimeException('config.php was not found. Copy config.php.sample to config.php first.');
        }
        return [];
    }

    $config = require $path;
    if (!is_array($config)) {
        $config = legacy_config_from_constants();
    }

    if (!is_array($config)) {
        throw new RuntimeException('config.php must return an array. Copy the current config.php.sample to config.php and fill in the values.');
    }

    $defaults = [
        'app' => [
            'name' => 'PHP Server Monitor',
            'base_url' => '',
            'debug' => false,
            'timezone' => 'UTC',
        ],
        'db' => [
            'host' => 'localhost',
            'port' => 3306,
            'name' => '',
            'user' => '',
            'pass' => '',
            'prefix' => 'monitor_',
        ],
        'security' => [
            'session_name' => 'psm_admin',
            'session_lifetime' => 1800,
            'password_cost' => 12,
        ],
        'cron' => [
            'token' => '',
            'lock_seconds' => 300,
        ],
    ];

    return array_replace_recursive($defaults, $config);
}

function legacy_config_from_constants(): ?array
{
    if (!defined('PSM_DB_NAME') || !defined('PSM_DB_USER') || !defined('PSM_DB_HOST')) {
        return null;
    }

    return [
        'app' => [
            'name' => 'PHP Server Monitor',
            'base_url' => defined('PSM_BASE_URL') ? (string)PSM_BASE_URL : '',
            'debug' => defined('PSM_DEBUG') ? (bool)PSM_DEBUG : false,
            'timezone' => date_default_timezone_get() ?: 'UTC',
        ],
        'db' => [
            'host' => (string)PSM_DB_HOST,
            'port' => defined('PSM_DB_PORT') && PSM_DB_PORT !== '' ? (int)PSM_DB_PORT : 3306,
            'name' => (string)PSM_DB_NAME,
            'user' => (string)PSM_DB_USER,
            'pass' => defined('PSM_DB_PASS') ? (string)PSM_DB_PASS : '',
            'prefix' => defined('PSM_DB_PREFIX') ? (string)PSM_DB_PREFIX : 'monitor_',
        ],
        'cron' => [
            'token' => defined('PSM_WEBCRON_KEY') ? (string)PSM_WEBCRON_KEY : '',
        ],
    ];
}

function configure_runtime(array $config): void
{
    date_default_timezone_set((string)($config['app']['timezone'] ?? 'UTC'));
    ini_set('display_errors', !empty($config['app']['debug']) ? '1' : '0');
    ini_set('display_startup_errors', !empty($config['app']['debug']) ? '1' : '0');
    error_reporting(!empty($config['app']['debug']) ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

function start_secure_session(array $security = []): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $name = (string)($security['session_name'] ?? 'psm_admin');
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_secure_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $target): void
{
    header('Location: ' . $target, true, 302);
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function app_url(string $path = 'index.php'): string
{
    return $path;
}
