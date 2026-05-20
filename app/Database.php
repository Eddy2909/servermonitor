<?php
declare(strict_types=1);

namespace ModernMonitor;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private PDO $pdo;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->prefix = $this->validatePrefix((string)($config['prefix'] ?? 'monitor_'));

        $host = (string)($config['host'] ?? 'localhost');
        $port = (int)($config['port'] ?? 3306);
        $name = (string)($config['name'] ?? '');
        $user = (string)($config['user'] ?? '');
        $pass = (string)($config['pass'] ?? '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('Database name and user must be configured in config.php.');
        }

        if (str_starts_with($host, ':')) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', substr($host, 1), $name);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
        }

        try {
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed.', 0, $exception);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function table(string $name): string
    {
        if (!preg_match('/^[a-z_]+$/', $name)) {
            throw new RuntimeException('Invalid table name.');
        }

        return '`' . $this->prefix . $name . '`';
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function execute(string $sql, array $params = []): int
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->rowCount();
    }

    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    private function validatePrefix(string $prefix): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            throw new RuntimeException('Invalid database table prefix.');
        }

        return $prefix;
    }
}
