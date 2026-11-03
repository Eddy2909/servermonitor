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
        throw new RuntimeException('config.php must return an array. Copy the current config.php.sample to config.php and fill in the values.');
    }

    $defaults = [
        'app' => [
            'name' => 'Server Monitor',
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
            'session_name' => 'server_monitor_admin',
            'session_lifetime' => 1800,
            'password_cost' => 12,
            'trusted_proxies' => [],
        ],
        'cron' => [
            'token' => '',
            'lock_seconds' => 300,
        ],
    ];

    $merged = array_replace_recursive($defaults, $config);
    if ((string)($merged['app']['name'] ?? '') === 'PHP Server ' . 'Monitor') {
        $merged['app']['name'] = 'Server Monitor';
    }

    return $merged;
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

    $name = (string)($security['session_name'] ?? 'server_monitor_admin');
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_secure_request($security),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_secure_request(array $security = []): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (!is_trusted_proxy($security)) {
        return false;
    }

    return isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
}

function is_trusted_proxy(array $security = []): bool
{
    $trusted = $security['trusted_proxies'] ?? [];
    if (!is_array($trusted) || $trusted === []) {
        return false;
    }

    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if ($remote === '') {
        return false;
    }

    foreach ($trusted as $proxy) {
        if ($remote === (string)$proxy) {
            return true;
        }
    }

    return false;
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

function send_security_headers(bool $allowFraming = false): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    if (!$allowFraming) {
        header('X-Frame-Options: SAMEORIGIN');
    }
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function json_response(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function app_url(string $path = 'index.php', string $baseUrl = ''): string
{
    $base = rtrim($baseUrl, '/');

    return $base === '' ? $path : $base . '/' . ltrim($path, '/');
}
