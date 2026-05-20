<?php
declare(strict_types=1);

namespace ModernMonitor;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string)$_SESSION['_csrf_token'];
    }

    public static function validateRequest(): bool
    {
        $submitted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($submitted) && hash_equals(self::token(), $submitted);
    }

    public static function requireValid(): void
    {
        if (!self::validateRequest()) {
            json_response(['ok' => false, 'message' => 'CSRF token is invalid.'], 419);
        }
    }
}
