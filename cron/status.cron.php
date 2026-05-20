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

    $db = new Database($config['db']);
    Schema::ensure($db);
    $repo = new ServerRepository($db);
    $settings = $repo->settings();

    if (PHP_SAPI !== 'cli') {
        $token = (string)($_GET['token'] ?? $_GET['cron_token'] ?? '');
        $expected = (string)($settings['cron_token'] ?: ($config['cron']['token'] ?? ''));
        if ($expected === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    if ((string)($settings['cron_enabled'] ?? '1') !== '1') {
        $repo->markCronFinished('disabled', 0, 0, 0, 'Cron is disabled.');
        echo 'OK - cron disabled';
        exit;
    }

    $started = microtime(true);
    $lockSeconds = (int)($settings['cron_lock_seconds'] ?: ($config['cron']['lock_seconds'] ?? 300));

    if (!$repo->acquireCronLock($lockSeconds)) {
        http_response_code(409);
        echo 'Cron already running.';
        exit;
    }

    $monitor = new Monitor();
    $results = [];
    $errors = 0;
    $alertAttempts = 0;
    $maxChecks = max(1, min(500, (int)($settings['cron_max_checks_per_run'] ?? 50)));
    $retryAttempts = max(0, min(5, (int)($settings['cron_retry_attempts'] ?? 1)));
    $retryDelay = max(0, min(60, (int)($settings['cron_retry_delay_seconds'] ?? 2)));
    $alertLimit = max(0, min(500, (int)($settings['cron_alert_limit_per_run'] ?? 20)));
    $inMaintenance = $repo->cronInMaintenance($settings);

    try {
        $repo->markCronStarted();
        $notifier = new EmailNotifier($repo);
        foreach ($repo->dueForCron($maxChecks) as $server) {
            $result = run_check_with_retries($monitor, $server, $retryAttempts, $retryDelay);
            $checked = $repo->recordCheck((int)$server['id'], $result);
            if ($checked['status'] === 'down') {
                $errors++;
            }
            if (!$inMaintenance && ($alertLimit === 0 || $alertAttempts < $alertLimit)) {
                if ($notifier->notifyIfNeeded($server, $checked, $result)) {
                    $alertAttempts++;
                }
            }
            $results[] = [
                'id' => (int)$checked['id'],
                'name' => $checked['name'],
                'status' => $checked['status'],
                'response_time_ms' => $checked['response_time_ms'],
            ];
        }
        $durationMs = (int)round((microtime(true) - $started) * 1000);
        $message = $inMaintenance
            ? 'Checked ' . count($results) . ' target(s). Notifications paused by maintenance window.'
            : 'Checked ' . count($results) . ' target(s).';
        $repo->markCronFinished('ok', $durationMs, count($results), $errors, $message);
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
    if (isset($repo)) {
        $durationMs = isset($started) ? (int)round((microtime(true) - $started) * 1000) : 0;
        $repo->markCronFinished('failed', $durationMs, isset($results) ? count($results) : 0, isset($errors) ? $errors + 1 : 1, $exception->getMessage());
        $repo->releaseCronLock();
    }
    echo $debug ? 'Cron failed: ' . $exception->getMessage() : 'Cron failed.';
}

function run_check_with_retries(Monitor $monitor, array $server, int $retries, int $delaySeconds): array
{
    $attempts = max(1, $retries + 1);
    $last = [];
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $last = $monitor->check($server);
        if (($last['status'] ?? 'down') === 'up') {
            if ($attempt > 1) {
                $last['error_message'] = null;
            }
            return $last;
        }
        if ($attempt < $attempts && $delaySeconds > 0) {
            sleep($delaySeconds);
        }
    }

    if ($attempts > 1) {
        $last['error_message'] = trim((string)($last['error_message'] ?? 'Check failed.') . ' After ' . $attempts . ' attempts.');
    }

    return $last;
}
