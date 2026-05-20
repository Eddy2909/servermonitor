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
    $token = (string)($_GET['token'] ?? $_GET['page'] ?? '');
    $page = $repo->publicPageByToken($token);

    if ($page === null) {
        http_response_code(404);
        echo 'Status page not found.';
        exit;
    }

    $settings = [
        'public_status_title' => $page['title'],
        'public_status_badge' => $page['badge'],
        'public_status_description' => $page['description'],
        'public_status_theme' => $page['theme'],
        'public_status_accent' => $page['accent'],
        'public_show_latency' => (string)$page['show_latency'],
        'public_show_uptime' => (string)$page['show_uptime'],
        'public_show_last_check' => (string)$page['show_last_check'],
        'public_show_incidents' => (string)$page['show_incidents'],
        'public_footer_note' => $page['footer_note'],
    ];

    $pageId = (int)$page['id'];
    $servers = $repo->publicPageServers($pageId);
    $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    $assetBase = ($scriptDir === '' ? '' : $scriptDir) . '/';
    echo View::render('public_status.php', [
        'appName' => (string)$config['app']['name'],
        'assetBase' => $assetBase,
        'publicPage' => $page,
        'settings' => $settings,
        'servers' => $servers,
        'stats' => $repo->publicPageStats($pageId),
        'recentChecks' => $repo->publicPageRecentChecks($pageId, 10),
        'chartData' => $repo->publicPageChartData($pageId),
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Status page unavailable.';
}
