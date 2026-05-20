<?php
$overallStatus = ((int)$stats['offline'] > 0) ? 'degraded' : 'operational';
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$settings['public_status_accent']) ? $settings['public_status_accent'] : '#5dd6a5';
$theme = $settings['public_status_theme'] === 'light' ? 'public-light' : 'public-dark';
$assetBase = (string)($assetBase ?? '');
$assetVersion = bin2hex(random_bytes(6));
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($settings['public_status_title']) ?></title>
    <link rel="stylesheet" href="<?= e($assetBase) ?>assets/app.css?v=<?= e($assetVersion) ?>">
</head>
<body class="public-page <?= e($theme) ?>" style="--public-accent: <?= e($accent) ?>">
    <main class="public-shell pro-status-shell">
        <header class="pro-status-hero">
            <div class="pro-status-copy">
                <p class="eyebrow"><?= e($settings['public_status_badge']) ?></p>
                <h1><?= e($settings['public_status_title']) ?></h1>
                <p class="muted"><?= e($settings['public_status_description']) ?></p>
            </div>
            <div class="overall-card <?= e($overallStatus) ?>">
                <span class="metric-label">Gesamtstatus</span>
                <strong><?= $overallStatus === 'operational' ? 'Operational' : 'Degraded' ?></strong>
                <small><?= e((string)$stats['online']) ?> online / <?= e((string)$stats['offline']) ?> offline</small>
            </div>
        </header>

        <section class="metric-grid public-metrics">
            <article class="metric-card">
                <span class="metric-label">Services</span>
                <strong><?= e(count($servers)) ?></strong>
                <small>oeffentlich sichtbar</small>
            </article>
            <article class="metric-card accent-ok">
                <span class="metric-label">Online</span>
                <strong><?= e($stats['online']) ?></strong>
                <small>erreichbar</small>
            </article>
            <article class="metric-card accent-danger">
                <span class="metric-label">Offline</span>
                <strong><?= e($stats['offline']) ?></strong>
                <small>Stoerungen</small>
            </article>
            <?php if ($settings['public_show_uptime'] === '1'): ?>
                <article class="metric-card">
                    <span class="metric-label">Uptime</span>
                    <strong><?= e($stats['avg_uptime']) ?>%</strong>
                    <small>Score</small>
                </article>
            <?php endif; ?>
        </section>

        <section class="chart-grid public-chart-grid" id="publicCharts">
            <article class="panel">
                <div class="panel-header compact">
                    <div>
                        <p class="eyebrow">Performance</p>
                        <h2>Latenzverlauf</h2>
                    </div>
                </div>
                <canvas id="publicLatencyChart" class="dashboard-chart" height="170"></canvas>
            </article>
            <article class="panel">
                <div class="panel-header compact">
                    <div>
                        <p class="eyebrow">Reliability</p>
                        <h2>Status Mix</h2>
                    </div>
                </div>
                <canvas id="publicStatusChart" class="dashboard-chart" height="170"></canvas>
            </article>
        </section>

        <section class="public-layout">
            <article class="panel public-services-panel">
                <div class="panel-header">
                    <div>
                        <p class="eyebrow">Live</p>
                        <h2>Services</h2>
                    </div>
                </div>
                <div class="public-status-list">
                    <?php foreach ($servers as $server): ?>
                        <div class="public-status-item pro-service-row">
                            <div>
                                <strong><?= e($server['name']) ?></strong>
                                <small><?= e($server['url']) ?></small>
                            </div>
                            <div class="public-service-meta">
                                <?php if ($settings['public_show_latency'] === '1'): ?>
                                    <span><?= e($server['response_time_ms'] ?? '-') ?> ms</span>
                                <?php endif; ?>
                                <?php if ($settings['public_show_last_check'] === '1'): ?>
                                    <span><?= e($server['last_checked_at'] ?: 'Noch nicht geprueft') ?></span>
                                <?php endif; ?>
                                <span class="status-pill <?= e($server['status']) ?>"><?= e($server['status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($servers)): ?>
                        <p class="muted">Keine oeffentlich sichtbaren Services konfiguriert.</p>
                    <?php endif; ?>
                </div>
            </article>

            <?php if ($settings['public_show_incidents'] === '1'): ?>
                <aside class="panel public-incidents-panel">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Recent</p>
                            <h2>Letzte Ereignisse</h2>
                        </div>
                    </div>
                    <div class="activity-list">
                        <?php foreach ($recentChecks as $check): ?>
                            <div class="activity-item">
                                <span class="dot <?= e($check['status']) ?>"></span>
                                <div>
                                    <strong><?= e($check['name']) ?></strong>
                                    <small><?= e($check['checked_at']) ?> / <?= e($check['response_time_ms'] ?? '-') ?> ms</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentChecks)): ?>
                            <p class="muted">Noch keine Ereignisse vorhanden.</p>
                        <?php endif; ?>
                    </div>
                </aside>
            <?php endif; ?>
        </section>

        <?php if (trim((string)$settings['public_footer_note']) !== ''): ?>
            <footer class="public-footer-note"><?= e($settings['public_footer_note']) ?></footer>
        <?php endif; ?>
    </main>
    <script type="application/json" id="publicChartData"><?= json_encode($chartData ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
    <script src="<?= e($assetBase) ?>assets/app.js?v=<?= e($assetVersion) ?>"></script>
</body>
</html>
