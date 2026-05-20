<?php
$activityRows = $activityChecks ?? $recentChecks ?? [];
$activityTotal = count($activityRows);
$activityUp = 0;
$activityDown = 0;
$activityLatencyTotal = 0;
$activityLatencyCount = 0;
$activityServices = [];
$activityLastChecked = '-';
foreach ($activityRows as $activityRow) {
    $activityStatus = (string)($activityRow['status'] ?? '');
    if ($activityStatus === 'up') {
        $activityUp++;
    }
    if ($activityStatus === 'down') {
        $activityDown++;
    }
    if (isset($activityRow['response_time_ms']) && $activityRow['response_time_ms'] !== null) {
        $activityLatencyTotal += (int)$activityRow['response_time_ms'];
        $activityLatencyCount++;
    }
    if (!empty($activityRow['server_id'])) {
        $activityServices[(string)$activityRow['server_id']] = true;
    }
}
if (!empty($activityRows[0]['checked_at'])) {
    $activityLastChecked = (string)$activityRows[0]['checked_at'];
}
$activityAvgLatency = $activityLatencyCount > 0 ? (int)round($activityLatencyTotal / $activityLatencyCount) : 0;
$firstPublicPageUrl = 'index.php?page=settings';
if (!empty($publicPages[0]['token'])) {
    $firstPublicPageUrl = 'status/' . (string)$publicPages[0]['token'];
}
$assetVersion = bin2hex(random_bytes(6));
$cronUrl = 'cron/status.cron.php?token=' . (string)($settings['cron_token'] ?? '');
$cronStatus = (string)($cronHealth['status'] ?? 'unknown');
$cronStatusLabel = (string)($cronHealth['label'] ?? 'Cronstatus unklar');
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($appName) ?> Dashboard</title>
    <link rel="stylesheet" href="assets/app.css?v=<?= e($assetVersion) ?>">
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand-block">
                <div>
                    <strong><?= e($appName) ?></strong>
                    <span>Monitor Console</span>
                </div>
            </div>
            <nav class="side-nav">
                <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="index.php">Dashboard</a>
                <a class="<?= $activePage === 'servers' ? 'active' : '' ?>" href="index.php?page=servers">Server</a>
                <a class="<?= $activePage === 'activity' ? 'active' : '' ?>" href="index.php?page=activity">Aktivitaet</a>
                <a class="<?= $activePage === 'settings' ? 'active' : '' ?>" href="index.php?page=settings">Einstellungen</a>
                <a href="<?= e($firstPublicPageUrl) ?>" target="_blank" rel="noopener">Public Page</a>
            </nav>
            <form method="post" action="index.php?action=logout" class="logout-form">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <button type="submit" class="btn ghost wide">Logout</button>
            </form>
        </aside>

        <main class="content">
            <header class="topbar">
                <div>
                    <p class="eyebrow"><?= $activePage === 'settings' ? 'Configuration' : ($activePage === 'activity' ? 'Timeline' : ($activePage === 'servers' ? 'Inventory' : 'Operations')) ?></p>
                    <h1><?= $activePage === 'settings' ? 'Einstellungen' : ($activePage === 'activity' ? 'Aktivitaet' : ($activePage === 'servers' ? 'Server' : 'Service Health')) ?></h1>
                </div>
                <div class="top-actions">
                    <span class="user-pill"><?= e($username) ?></span>
                    <button type="button" class="btn primary" data-open-modal onclick="var m=document.getElementById('serverModal'); if(window.serverMonitorOpenModal){window.serverMonitorOpenModal(null);} else if(m){m.classList.add('is-open');m.style.display='grid';m.setAttribute('aria-hidden','false');} return false;">Neuer Check</button>
                </div>
            </header>

            <?php if ($activePage === 'dashboard'): ?>
            <section class="metric-grid" aria-label="Status Kennzahlen">
                <article class="metric-card">
                    <span class="metric-label">Targets</span>
                    <strong><?= e($stats['total']) ?></strong>
                    <small>ueberwachte Eintraege</small>
                </article>
                <article class="metric-card accent-ok">
                    <span class="metric-label">Online</span>
                    <strong><?= e($stats['online']) ?></strong>
                    <small>aktuell erreichbar</small>
                </article>
                <article class="metric-card accent-danger">
                    <span class="metric-label">Offline</span>
                    <strong><?= e($stats['offline']) ?></strong>
                    <small>brauchen Aufmerksamkeit</small>
                </article>
                <article class="metric-card">
                    <span class="metric-label">Avg. Latency</span>
                    <strong><?= e($stats['avg_latency']) ?> ms</strong>
                    <small><?= e($stats['avg_uptime']) ?>% Uptime Score</small>
                </article>
            </section>

            <section class="chart-grid" id="charts">
                <article class="panel">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Auswertung</p>
                            <h2>Latenzverlauf</h2>
                        </div>
                    </div>
                    <canvas id="latencyChart" class="dashboard-chart" height="170"></canvas>
                </article>
                <article class="panel">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Auswertung</p>
                            <h2>Status Mix</h2>
                        </div>
                    </div>
                    <canvas id="statusChart" class="dashboard-chart" height="170"></canvas>
                </article>
            </section>
            <?php endif; ?>

            <?php if ($activePage === 'dashboard' || $activePage === 'servers'): ?>
            <section class="dashboard-grid <?= $activePage === 'dashboard' ? '' : 'single-grid' ?>">
                <div class="panel wide-panel" id="servers">
                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">Inventory</p>
                            <h2>Server & Websites</h2>
                        </div>
                        <div class="table-tools">
                            <input type="search" id="tableSearch" placeholder="Suchen">
                            <select id="statusFilter" aria-label="Status filtern">
                                <option value="">Alle Status</option>
                                <option value="up">Online</option>
                                <option value="down">Offline</option>
                                <option value="unknown">Unbekannt</option>
                            </select>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table" id="serversTable">
                            <thead>
                                <tr>
                                    <th data-sort="name">Name</th>
                                    <th data-sort="type">Typ</th>
                                    <th data-sort="status">Status</th>
                                    <th data-sort="latency">Latenz</th>
                                    <th data-sort="score">Score</th>
                                    <th data-sort="checked">Letzter Check</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servers as $server): ?>
                                    <?= ModernMonitor\View::render('partials/server_row.php', ['server' => $server]) ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($activePage === 'dashboard'): ?>
                <aside class="panel" id="activity">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Signals</p>
                            <h2>Letzte Checks</h2>
                        </div>
                    </div>
                    <div class="activity-list">
                        <?php if (empty($recentChecks)): ?>
                            <p class="muted">Noch keine Checks vorhanden.</p>
                        <?php endif; ?>
                        <?php foreach ($recentChecks as $check): ?>
                            <div class="activity-item">
                                <span class="dot <?= e($check['status']) ?>"></span>
                                <div>
                                    <strong><?= e($check['name']) ?></strong>
                                    <small><?= e($check['checked_at']) ?> / <?= e($check['response_time_ms'] ?? '-') ?> ms</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if ($activePage === 'activity'): ?>
            <section class="activity-page" id="activity">
                <div class="metric-grid activity-metric-grid" aria-label="Aktivitaet Kennzahlen">
                    <article class="metric-card">
                        <span class="metric-label">Checks</span>
                        <strong><?= e($activityTotal) ?></strong>
                        <small>letzte gespeicherte Abfragen</small>
                    </article>
                    <article class="metric-card accent-ok">
                        <span class="metric-label">Erfolgreich</span>
                        <strong><?= e($activityUp) ?></strong>
                        <small>mit Status online</small>
                    </article>
                    <article class="metric-card accent-danger">
                        <span class="metric-label">Fehlerhaft</span>
                        <strong><?= e($activityDown) ?></strong>
                        <small>mit Status offline</small>
                    </article>
                    <article class="metric-card">
                        <span class="metric-label">Avg. Latenz</span>
                        <strong><?= e($activityAvgLatency) ?> ms</strong>
                        <small><?= e(count($activityServices)) ?> Services im Protokoll</small>
                    </article>
                </div>

                <div class="panel wide-panel activity-log-panel">
                    <div class="panel-header">
                        <div>
                            <p class="eyebrow">Audit Trail</p>
                            <h2>Abfrage-Protokoll</h2>
                        </div>
                        <div class="table-tools activity-tools">
                            <input type="search" id="activitySearch" placeholder="Protokoll suchen">
                            <select id="activityStatusFilter" aria-label="Status filtern">
                                <option value="">Alle Status</option>
                                <option value="up">Online</option>
                                <option value="down">Offline</option>
                            </select>
                            <select id="activityTypeFilter" aria-label="Typ filtern">
                                <option value="">Alle Typen</option>
                                <option value="website">Website</option>
                                <option value="tcp">Service</option>
                                <option value="ping">Ping</option>
                            </select>
                        </div>
                    </div>
                    <div class="activity-strip">
                        <div>
                            <span>Letzte Abfrage</span>
                            <strong><?= e($activityLastChecked) ?></strong>
                        </div>
                        <div>
                            <span>Treffer</span>
                            <strong id="activityVisibleCount"><?= e($activityTotal) ?></strong>
                        </div>
                    </div>
                    <div class="table-wrap">
                        <table class="data-table activity-table" id="activityTable">
                            <thead>
                                <tr>
                                    <th data-sort="server">Service</th>
                                    <th data-sort="type">Typ</th>
                                    <th data-sort="status">Status</th>
                                    <th data-sort="latency">Latenz</th>
                                    <th data-sort="http">HTTP</th>
                                    <th data-sort="checked">Zeitpunkt</th>
                                    <th data-sort="error">Fehler / Antwort</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($activityRows)): ?>
                                <tr data-status="" data-type="" data-search="noch keine abfragen vorhanden">
                                    <td colspan="7" class="muted">Noch keine Abfragen vorhanden.</td>
                                </tr>
                                <?php endif; ?>
                                <?php foreach ($activityRows as $check): ?>
                                <?php
                                    $checkStatus = (string)($check['status'] ?? 'unknown');
                                    $checkType = (string)($check['type'] ?? 'website');
                                    $checkLatency = $check['response_time_ms'] === null ? '-' : (string)$check['response_time_ms'] . ' ms';
                                    $checkHttp = $check['http_code'] === null ? '-' : (string)$check['http_code'];
                                    $checkError = trim((string)($check['error_message'] ?? ''));
                                    $checkMessage = $checkError !== '' ? $checkError : 'OK';
                                    $checkSearch = strtolower(trim(
                                        (string)($check['name'] ?? '') . ' ' .
                                        (string)($check['url'] ?? '') . ' ' .
                                        $checkType . ' ' .
                                        $checkStatus . ' ' .
                                        $checkHttp . ' ' .
                                        $checkMessage . ' ' .
                                        (string)($check['checked_at'] ?? '')
                                    ));
                                ?>
                                <tr
                                    data-status="<?= e($checkStatus) ?>"
                                    data-type="<?= e($checkType) ?>"
                                    data-search="<?= e($checkSearch) ?>"
                                >
                                    <td>
                                        <div class="target-cell activity-target">
                                            <strong><?= e($check['name']) ?></strong>
                                            <span><?= e($check['url'] ?? '-') ?></span>
                                            <span>Check #<?= e($check['id'] ?? '-') ?> / Server #<?= e($check['server_id'] ?? '-') ?></span>
                                        </div>
                                    </td>
                                    <td><span class="type-chip"><?= e(strtoupper($checkType)) ?></span></td>
                                    <td><span class="status-pill <?= e($checkStatus) ?>"><?= e($checkStatus) ?></span></td>
                                    <td data-value="<?= e($check['response_time_ms'] ?? 0) ?>"><?= e($checkLatency) ?></td>
                                    <td data-value="<?= e($check['http_code'] ?? 0) ?>"><?= e($checkHttp) ?></td>
                                    <td data-value="<?= e($check['checked_at'] ?? '') ?>"><?= e($check['checked_at'] ?? '-') ?></td>
                                    <td data-value="<?= e($checkMessage) ?>">
                                        <span class="activity-message <?= $checkError !== '' ? 'is-error' : 'is-ok' ?>"><?= e($checkMessage) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($activePage === 'settings'): ?>
            <section class="settings-page" id="settings">
                <div class="settings-tabs" role="tablist" aria-label="Einstellungsbereiche">
                    <button type="button" class="settings-tab is-active" data-settings-tab="mail">E-Mail</button>
                    <button type="button" class="settings-tab" data-settings-tab="cron">Cronjob</button>
                    <button type="button" class="settings-tab" data-settings-tab="public">Public Pages</button>
                </div>
                <div class="settings-panels">
                <article class="panel settings-panel is-active" data-settings-panel="mail">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Mail</p>
                            <h2>E-Mail Benachrichtigung</h2>
                        </div>
                    </div>
                    <form id="settingsForm" class="form-grid">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="email_enabled" value="0">
                        <label class="toggle-line"><input type="checkbox" name="email_enabled" value="1" <?= $settings['email_enabled'] === '1' ? 'checked' : '' ?>> Aktiv</label>
                        <label>Absender E-Mail<input name="email_from" value="<?= e($settings['email_from']) ?>"></label>
                        <label>Absender Name<input name="email_from_name" value="<?= e($settings['email_from_name']) ?>"></label>
                        <label>Standard Empfaenger<input name="email_default_to" value="<?= e($settings['email_default_to']) ?>"></label>
                        <label>Betreff Prefix<input name="email_subject_prefix" value="<?= e($settings['email_subject_prefix']) ?>"></label>
                        <label>Warnschwelle fehlgeschlagener Checks<input name="warning_threshold_checks" type="number" min="1" max="20" value="<?= e($settings['warning_threshold_checks']) ?>"></label>
                        <input type="hidden" name="public_status_enabled" value="<?= e($settings['public_status_enabled']) ?>">
                        <input type="hidden" name="public_status_title" value="<?= e($settings['public_status_title']) ?>">
                        <input type="hidden" name="public_status_description" value="<?= e($settings['public_status_description']) ?>">
                        <input type="hidden" name="public_status_badge" value="<?= e($settings['public_status_badge']) ?>">
                        <input type="hidden" name="public_status_theme" value="<?= e($settings['public_status_theme']) ?>">
                        <input type="hidden" name="public_status_accent" value="<?= e($settings['public_status_accent']) ?>">
                        <input type="hidden" name="public_show_latency" value="<?= e($settings['public_show_latency']) ?>">
                        <input type="hidden" name="public_show_uptime" value="<?= e($settings['public_show_uptime']) ?>">
                        <input type="hidden" name="public_show_last_check" value="<?= e($settings['public_show_last_check']) ?>">
                        <input type="hidden" name="public_show_incidents" value="<?= e($settings['public_show_incidents']) ?>">
                        <input type="hidden" name="public_footer_note" value="<?= e($settings['public_footer_note']) ?>">
                        <div class="modal-actions">
                            <button class="btn primary" type="submit">Einstellungen speichern</button>
                        </div>
                    </form>
                </article>

                <article class="panel cron-panel settings-panel" data-settings-panel="cron">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Automation</p>
                            <h2>Cronjob</h2>
                        </div>
                        <span class="status-pill <?= e($cronStatus) ?>"><?= e($cronStatusLabel) ?></span>
                    </div>
                    <div class="cron-summary">
                        <div>
                            <span>Letzter Lauf</span>
                            <strong><?= e($settings['cron_last_finished_at'] ?: '-') ?></strong>
                        </div>
                        <div>
                            <span>Geprueft</span>
                            <strong><?= e($settings['cron_last_checked_count'] ?? '0') ?></strong>
                        </div>
                        <div>
                            <span>Fehler</span>
                            <strong><?= e($settings['cron_last_error_count'] ?? '0') ?></strong>
                        </div>
                        <div>
                            <span>Dauer</span>
                            <strong><?= e($settings['cron_last_duration_ms'] ?? '0') ?> ms</strong>
                        </div>
                    </div>
                    <p class="muted"><?= e($settings['cron_last_message'] ?: 'Noch kein Laufprotokoll vorhanden.') ?></p>
                    <form id="cronSettingsForm" class="form-grid">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="cron_enabled" value="0">
                        <input type="hidden" name="cron_maintenance_enabled" value="0">
                        <label class="toggle-line"><input type="checkbox" name="cron_enabled" value="1" <?= $settings['cron_enabled'] === '1' ? 'checked' : '' ?>> Cron aktiv</label>
                        <label>Cron-URL<input id="cronUrl" value="<?= e($cronUrl) ?>" readonly></label>
                        <label>Cron-Token<input name="cron_token" id="cronToken" value="<?= e($settings['cron_token']) ?>"></label>
                        <label>Lock-Dauer Sekunden<input name="cron_lock_seconds" type="number" min="30" max="3600" value="<?= e($settings['cron_lock_seconds']) ?>"></label>
                        <label>Max. Checks pro Lauf<input name="cron_max_checks_per_run" type="number" min="1" max="500" value="<?= e($settings['cron_max_checks_per_run']) ?>"></label>
                        <label>Retries je Fehler<input name="cron_retry_attempts" type="number" min="0" max="5" value="<?= e($settings['cron_retry_attempts']) ?>"></label>
                        <label>Retry-Pause Sekunden<input name="cron_retry_delay_seconds" type="number" min="0" max="60" value="<?= e($settings['cron_retry_delay_seconds']) ?>"></label>
                        <label>Default Timeout Sekunden<input name="cron_default_timeout_seconds" type="number" min="1" max="60" value="<?= e($settings['cron_default_timeout_seconds']) ?>"></label>
                        <label>Max. Alerts pro Lauf<input name="cron_alert_limit_per_run" type="number" min="0" max="500" value="<?= e($settings['cron_alert_limit_per_run']) ?>" placeholder="0 = unbegrenzt"></label>
                        <label>Health Warnung nach Minuten<input name="cron_health_grace_minutes" type="number" min="1" max="1440" value="<?= e($settings['cron_health_grace_minutes']) ?>"></label>
                        <label class="toggle-line"><input type="checkbox" name="cron_maintenance_enabled" value="1" <?= $settings['cron_maintenance_enabled'] === '1' ? 'checked' : '' ?>> Wartungsfenster pausiert Alerts</label>
                        <label>Wartung Start<input name="cron_maintenance_start" type="time" value="<?= e($settings['cron_maintenance_start']) ?>"></label>
                        <label>Wartung Ende<input name="cron_maintenance_end" type="time" value="<?= e($settings['cron_maintenance_end']) ?>"></label>
                        <div class="modal-actions">
                            <button class="btn ghost" type="button" id="rotateCronToken">Token rotieren</button>
                            <button class="btn primary" type="submit">Cron speichern</button>
                        </div>
                    </form>
                </article>

                <article class="panel public-pages-panel settings-panel" data-settings-panel="public">
                    <div class="panel-header compact">
                        <div>
                            <p class="eyebrow">Public</p>
                            <h2>Status Pages</h2>
                        </div>
                    </div>
                    <form class="public-page-form form-grid" data-public-page-form>
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <label class="toggle-line"><input type="checkbox" name="enabled" value="1" checked> Neue Page aktiv</label>
                        <label>Titel<input name="title" value="System Status"></label>
                        <label>Badge / Eyebrow<input name="badge" value="Live infrastructure status"></label>
                        <label>Beschreibung<input name="description" value="Live status of monitored services."></label>
                        <label>Theme
                            <select name="theme">
                                <option value="dark">Dark</option>
                                <option value="light">Light</option>
                            </select>
                        </label>
                        <label>Akzentfarbe<input name="accent" value="#5dd6a5" placeholder="#5dd6a5"></label>
                        <label>Footer Hinweis<input name="footer_note"></label>
                        <label class="toggle-line"><input type="checkbox" name="show_latency" value="1" checked> Latenz anzeigen</label>
                        <label class="toggle-line"><input type="checkbox" name="show_uptime" value="1" checked> Uptime anzeigen</label>
                        <label class="toggle-line"><input type="checkbox" name="show_last_check" value="1" checked> Letzten Check anzeigen</label>
                        <label class="toggle-line"><input type="checkbox" name="show_incidents" value="1" checked> Stoerungen anzeigen</label>
                        <div class="service-picker">
                            <span class="metric-label">Dienste</span>
                            <?php foreach ($servers as $server): ?>
                                <label class="service-check"><input type="checkbox" name="server_ids[]" value="<?= e($server['id']) ?>" <?= (int)($server['enabled'] ?? 0) === 1 ? 'checked' : '' ?>> <?= e($server['name']) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-actions">
                            <button class="btn primary" type="submit">Public Page anlegen</button>
                        </div>
                    </form>

                    <div class="public-page-list">
                        <?php foreach ($publicPages ?? [] as $publicPage): ?>
                            <?php
                                $selectedServers = array_filter(explode(',', (string)($publicPage['server_ids'] ?? '')));
                                $publicUrl = 'status/' . (string)$publicPage['token'];
                            ?>
                            <details class="public-page-card">
                                <summary class="public-page-card-head">
                                    <div>
                                        <span class="metric-label"><?= (int)$publicPage['enabled'] === 1 ? 'Aktiv' : 'Inaktiv' ?></span>
                                        <strong><?= e($publicPage['title']) ?></strong>
                                        <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"><?= e($publicUrl) ?></a>
                                    </div>
                                    <span class="status-pill <?= (int)$publicPage['enabled'] === 1 ? 'up' : 'unknown' ?>"><?= e((string)$publicPage['assigned_count']) ?> Services</span>
                                </summary>
                                <form class="public-page-form" data-public-page-form>
                                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= e($publicPage['id']) ?>">
                                <div class="form-grid compact-public-form">
                                    <label class="toggle-line"><input type="checkbox" name="enabled" value="1" <?= (int)$publicPage['enabled'] === 1 ? 'checked' : '' ?>> Aktiv</label>
                                    <label>Titel<input name="title" value="<?= e($publicPage['title']) ?>"></label>
                                    <label>Badge<input name="badge" value="<?= e($publicPage['badge']) ?>"></label>
                                    <label>Beschreibung<input name="description" value="<?= e($publicPage['description']) ?>"></label>
                                    <label>Theme
                                        <select name="theme">
                                            <option value="dark" <?= $publicPage['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                                            <option value="light" <?= $publicPage['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                                        </select>
                                    </label>
                                    <label>Akzentfarbe<input name="accent" value="<?= e($publicPage['accent']) ?>"></label>
                                    <label>Footer Hinweis<input name="footer_note" value="<?= e($publicPage['footer_note']) ?>"></label>
                                    <label class="toggle-line"><input type="checkbox" name="show_latency" value="1" <?= (int)$publicPage['show_latency'] === 1 ? 'checked' : '' ?>> Latenz</label>
                                    <label class="toggle-line"><input type="checkbox" name="show_uptime" value="1" <?= (int)$publicPage['show_uptime'] === 1 ? 'checked' : '' ?>> Uptime</label>
                                    <label class="toggle-line"><input type="checkbox" name="show_last_check" value="1" <?= (int)$publicPage['show_last_check'] === 1 ? 'checked' : '' ?>> Letzter Check</label>
                                    <label class="toggle-line"><input type="checkbox" name="show_incidents" value="1" <?= (int)$publicPage['show_incidents'] === 1 ? 'checked' : '' ?>> Stoerungen</label>
                                </div>
                                <div class="service-picker">
                                    <span class="metric-label">Zugewiesene Dienste</span>
                                    <?php foreach ($servers as $server): ?>
                                        <label class="service-check"><input type="checkbox" name="server_ids[]" value="<?= e($server['id']) ?>" <?= in_array((string)$server['id'], $selectedServers, true) ? 'checked' : '' ?>> <?= e($server['name']) ?></label>
                                    <?php endforeach; ?>
                                </div>
                                <div class="modal-actions">
                                    <a class="btn ghost" href="<?= e($publicUrl) ?>" target="_blank" rel="noopener">Oeffnen</a>
                                    <button class="btn ghost danger-action" type="button" data-delete-public-page="<?= e($publicPage['id']) ?>">Loeschen</button>
                                    <button class="btn primary" type="submit">Speichern</button>
                                </div>
                                </form>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </article>
                </div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <div class="modal-backdrop" id="serverModal" aria-hidden="true" style="display:none">
        <form class="modal-panel" id="serverForm">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" id="serverId">
            <div class="modal-header">
                <h2 id="modalTitle">Neuer Check</h2>
                <button type="button" class="icon-btn" id="modalCloseButton" data-close-modal aria-label="Schliessen" onclick="var m=document.getElementById('serverModal'); if(m){m.classList.remove('is-open');m.style.display='none';m.setAttribute('aria-hidden','true');} return false;">x</button>
            </div>
            <div class="form-grid">
                <label>Name<input name="name" id="serverName" required maxlength="190"></label>
                <label>Ziel / URL<input name="url" id="serverUrl" required maxlength="500" placeholder="https://example.com"></label>
                <label>Typ
                    <select name="type" id="serverType">
                        <option value="website">Website</option>
                        <option value="tcp">TCP Service</option>
                        <option value="ping">Ping</option>
                    </select>
                </label>
                <div class="service-port-fields" id="servicePortFields">
                    <label>Port-Auswahl
                        <select id="serverPortPreset">
                            <option value="custom">Custom Port</option>
                            <option value="80">HTTP (80)</option>
                            <option value="443">HTTPS (443)</option>
                            <option value="21">FTP (21)</option>
                            <option value="25">SMTP (25)</option>
                            <option value="465">SMTP Secure (465)</option>
                            <option value="110">POP3 (110)</option>
                            <option value="995">POP3 Secure (995)</option>
                            <option value="143">IMAP (143)</option>
                            <option value="993">IMAP over SSL (993)</option>
                            <option value="22">SSH (22)</option>
                            <option value="389">LDAP (389)</option>
                            <option value="3306">MySQL (3306)</option>
                            <option value="115">SFTP (115)</option>
                            <option value="43">WHOIS (43)</option>
                            <option selected value="53">BIND (53)</option>
                            <option value="3389">RDP (3389)</option>
                        </select>
                    </label>
                    <label id="serverCustomPortWrap">Custom Port<input name="port" id="serverPort" type="number" min="1" max="65535"></label>
                </div>
                <label>Methode
                    <select name="method" id="serverMethod">
                        <option value="GET">GET</option>
                        <option value="HEAD">HEAD</option>
                    </select>
                </label>
                <label>Statusregel<input name="expected_status" id="serverExpectedStatus" value="200-399"></label>
                <label>Text muss enthalten<input name="expected_text" id="serverExpectedText" maxlength="255"></label>
                <label>Timeout<input name="timeout_seconds" id="serverTimeout" type="number" min="1" max="60" value="<?= e($settings['cron_default_timeout_seconds'] ?? '10') ?>" data-default="<?= e($settings['cron_default_timeout_seconds'] ?? '10') ?>"></label>
                <label>Check-Intervall Minuten<input name="check_interval_minutes" id="serverCheckInterval" type="number" min="1" max="1440" value="5"></label>
                <label class="toggle-line"><input name="enabled" id="serverEnabled" type="checkbox" value="1" checked> Aktiv</label>
                <label class="toggle-line"><input name="public_visible" id="serverPublicVisible" type="checkbox" value="1" checked> Public sichtbar</label>
                <label class="toggle-line"><input name="notify_enabled" id="serverNotifyEnabled" type="checkbox" value="1"> E-Mail aktiv</label>
                <label>Empfaenger<input name="notify_email" id="serverNotifyEmail" maxlength="190"></label>
                <label class="toggle-line"><input name="notify_on_down" id="serverNotifyOnDown" type="checkbox" value="1" checked> Bei Ausfall</label>
                <label class="toggle-line"><input name="notify_on_recovery" id="serverNotifyOnRecovery" type="checkbox" value="1" checked> Bei Erholung</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn ghost" data-close-modal onclick="var m=document.getElementById('serverModal'); if(m){m.classList.remove('is-open');m.style.display='none';m.setAttribute('aria-hidden','true');} return false;">Abbrechen</button>
                <button type="submit" class="btn primary">Speichern</button>
            </div>
        </form>
    </div>

    <div class="modal-backdrop" id="detailsModal" aria-hidden="true" style="display:none">
        <div class="modal-panel details-panel">
            <div class="modal-header">
                <h2 id="detailsTitle">Details</h2>
                <button type="button" class="icon-btn" data-close-details aria-label="Schliessen">x</button>
            </div>
            <div id="detailsBody" class="details-body"></div>
        </div>
    </div>

    <div id="toast" class="toast" hidden></div>
    <script type="application/json" id="chartData"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
    <script src="assets/app.js?v=<?= e($assetVersion) ?>"></script>
</body>
</html>
