<?php
declare(strict_types=1);

namespace ModernMonitor;

final class Auth
{
    private Database $db;
    private array $security;

    public function __construct(Database $db, array $config)
    {
        $this->db = $db;
        $this->security = $config['security'] ?? [];
        start_secure_session($this->security);
        $this->expireIdleSession();
    }

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOGIN_WINDOW_SECONDS = 900;

    public function login(string $username, string $password): bool
    {
        $user = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('users') . ' WHERE username = :username LIMIT 1',
            ['username' => trim($username)]
        );

        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            if ($user === null) {
                static $dummyHash = null;
                $dummyHash ??= password_hash('invalid-login-padding', PASSWORD_DEFAULT, [
                    'cost' => (int)($this->security['password_cost'] ?? 12),
                ]);
                password_verify($password, $dummyHash);
            }
            $this->recordLoginFailure($username);
            return false;
        }

        session_regenerate_id(true);
        unset($_SESSION['_csrf_token']);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = (string)$user['username'];
        $_SESSION['role'] = (string)$user['role'];
        $_SESSION['fingerprint'] = $this->fingerprint();
        $_SESSION['last_activity'] = time();
        $_SESSION['regenerated_at'] = time();

        $this->db->execute(
            'UPDATE ' . $this->db->table('users') . ' SET last_login_at = NOW() WHERE id = :id',
            ['id' => (int)$user['id']]
        );

        $this->recordLoginSuccess($username);

        $cost = (int)($this->security['password_cost'] ?? 12);
        if (password_needs_rehash((string)$user['password_hash'], PASSWORD_DEFAULT, ['cost' => $cost])) {
            $this->db->execute(
                'UPDATE ' . $this->db->table('users') . ' SET password_hash = :hash WHERE id = :id',
                [
                    'hash' => password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]),
                    'id' => (int)$user['id'],
                ]
            );
        }

        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['fingerprint'])
            && hash_equals((string)$_SESSION['fingerprint'], $this->fingerprint());
    }

    public function requireLogin(): void
    {
        if (!$this->check()) {
            redirect('index.php?page=login');
        }
    }

    public function username(): string
    {
        return (string)($_SESSION['username'] ?? '');
    }

    private function expireIdleSession(): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $lifetime = (int)($this->security['session_lifetime'] ?? 1800);
        $lastActivity = (int)($_SESSION['last_activity'] ?? 0);
        if ($lastActivity > 0 && time() - $lastActivity > $lifetime) {
            $this->logout();
            return;
        }

        $_SESSION['last_activity'] = time();
        $regeneratedAt = (int)($_SESSION['regenerated_at'] ?? 0);
        if ($regeneratedAt === 0 || time() - $regeneratedAt > 300) {
            session_regenerate_id(true);
            $_SESSION['regenerated_at'] = time();
        }
    }

    private function fingerprint(): string
    {
        return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? 'cli'));
    }

    /**
     * Einfache Brute-Force-Bremse: zaehlt Fehlversuche pro IP+Konto in einem
     * Zeitfenster. Kein permanentes Lock, damit sich Nutzer nicht aussperren.
     */
    public function loginLockRemaining(string $username): int
    {
        $row = $this->db->fetchOne(
            'SELECT value FROM ' . $this->db->table('settings') . ' WHERE name = :name',
            ['name' => $this->loginThrottleKey($username)]
        );
        if ($row === null || !isset($row['value'])) {
            return 0;
        }

        [$count, $first] = array_pad(explode('|', (string)$row['value']), 2, '0');
        $count = (int)$count;
        $first = (int)$first;
        if ($count < self::MAX_LOGIN_ATTEMPTS || $first <= 0 || time() - $first > self::LOGIN_WINDOW_SECONDS) {
            return 0;
        }

        return max(0, ($first + self::LOGIN_WINDOW_SECONDS) - time());
    }

    private function recordLoginFailure(string $username): void
    {
        $key = $this->loginThrottleKey($username);
        $row = $this->db->fetchOne(
            'SELECT value FROM ' . $this->db->table('settings') . ' WHERE name = :name',
            ['name' => $key]
        );

        $now = time();
        $count = 0;
        $first = $now;
        if ($row !== null && isset($row['value'])) {
            [$storedCount, $storedFirst] = array_pad(explode('|', (string)$row['value']), 2, '0');
            $storedCount = (int)$storedCount;
            $storedFirst = (int)$storedFirst;
            if ($storedFirst > 0 && $now - $storedFirst <= self::LOGIN_WINDOW_SECONDS) {
                $count = $storedCount;
                $first = $storedFirst;
            }
        }

        $this->db->execute(
            'INSERT INTO ' . $this->db->table('settings') . ' (name, value) VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            ['name' => $key, 'value' => ($count + 1) . '|' . $first]
        );
    }

    private function recordLoginSuccess(string $username): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('settings') . ' WHERE name = :name',
            ['name' => $this->loginThrottleKey($username)]
        );
    }

    private function loginThrottleKey(string $username): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'cli');

        return 'login_fail_' . substr(hash('sha256', $ip . '|' . strtolower(trim($username))), 0, 24);
    }
}
