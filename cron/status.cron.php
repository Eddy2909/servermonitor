<?php
declare(strict_types=1);

use ModernMonitor\Database;
use ModernMonitor\EmailNotifier;
use ModernMonitor\Monitor;
use ModernMonitor\Schema;
use ModernMonitor\ServerRepository;

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $config = load_config();
    configure_runtime($config);

    if (PHP_SAPI !== 'cli') {
        $token = (string)($_GET['token'] ?? $_GET['cron_token'] ?? '');
        $expected = (string)($config['cron']['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    $db = new Database($config['db']);
    Schema::ensure($db);
    $repo = new ServerRepository($db);
    $lockSeconds = (int)($config['cron']['lock_seconds'] ?? 300);

    if (!$repo->acquireCronLock($lockSeconds)) {
        http_response_code(409);
        echo 'Cron already running.';
        exit;
    }

    $monitor = new Monitor();
    $results = [];

    try {
        $notifier = new EmailNotifier($repo);
        foreach ($repo->enabled() as $server) {
            $result = $monitor->check($server);
            $checked = $repo->recordCheck((int)$server['id'], $result);
            $notifier->notifyIfNeeded($server, $checked, $result);
            $results[] = [
                'id' => (int)$checked['id'],
                'name' => $checked['name'],
                'status' => $checked['status'],
                'response_time_ms' => $checked['response_time_ms'],
            ];
        }
    } finally {
        $repo->releaseCronLock();
    }

    if (PHP_SAPI !== 'cli' && ($_GET['format'] ?? '') === 'json') {
        json_response(['ok' => true, 'checked' => count($results), 'results' => $results]);
    }

    echo 'OK - checked ' . count($results) . ' target(s)';
} catch (Throwable $exception) {
    http_response_code(500);
    $debug = isset($config) && !empty($config['app']['debug']);
    echo $debug ? 'Cron failed: ' . $exception->getMessage() : 'Cron failed.';
}
