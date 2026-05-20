<?php
declare(strict_types=1);

use ModernMonitor\Database;
use ModernMonitor\Schema;
use ModernMonitor\ServerRepository;
use ModernMonitor\View;

require __DIR__ . '/app/bootstrap.php';

try {
    $config = load_config();
    configure_runtime($config);
    $db = new Database($config['db']);
    Schema::ensure($db);
    $repo = new ServerRepository($db);
    $settings = $repo->settings();

    if ($settings['public_status_enabled'] !== '1') {
        http_response_code(404);
        echo 'Public status page is disabled.';
        exit;
    }

    $servers = $repo->publicServers();
    echo View::render('public_status.php', [
        'appName' => (string)$config['app']['name'],
        'settings' => $settings,
        'servers' => $servers,
        'stats' => $repo->publicStats(),
        'recentChecks' => $repo->publicRecentChecks(10),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Status page unavailable.';
}
