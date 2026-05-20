<?php
$status = (string)($server['status'] ?? 'unknown');
$latency = $server['response_time_ms'] === null ? '-' : (string)$server['response_time_ms'] . ' ms';
$checked = $server['last_checked_at'] ?: '-';
$score = number_format((float)($server['uptime_score'] ?? 0), 1);
?>
<tr
    data-server-id="<?= e($server['id']) ?>"
    data-name="<?= e($server['name']) ?>"
    data-url="<?= e($server['url']) ?>"
    data-type="<?= e($server['type']) ?>"
    data-port="<?= e($server['port'] ?? '') ?>"
    data-method="<?= e($server['method']) ?>"
    data-expected-status="<?= e($server['expected_status']) ?>"
    data-expected-text="<?= e($server['expected_text']) ?>"
    data-timeout="<?= e($server['timeout_seconds']) ?>"
    data-check-interval="<?= e($server['check_interval_minutes'] ?? 5) ?>"
    data-enabled="<?= e($server['enabled']) ?>"
    data-public-visible="<?= e($server['public_visible'] ?? 1) ?>"
    data-notify-enabled="<?= e($server['notify_enabled'] ?? 0) ?>"
    data-notify-email="<?= e($server['notify_email'] ?? '') ?>"
    data-notify-on-down="<?= e($server['notify_on_down'] ?? 1) ?>"
    data-notify-on-recovery="<?= e($server['notify_on_recovery'] ?? 1) ?>"
    data-status="<?= e($status) ?>"
>
    <td>
        <div class="target-cell">
            <strong><?= e($server['name']) ?></strong>
            <span><?= e($server['url']) ?></span>
        </div>
    </td>
    <td><span class="type-chip"><?= e(strtoupper((string)$server['type'])) ?></span></td>
    <td><span class="status-pill <?= e($status) ?>"><?= e($status) ?></span></td>
    <td data-value="<?= e($server['response_time_ms'] ?? 0) ?>"><?= e($latency) ?></td>
    <td data-value="<?= e($score) ?>">
        <div class="score-line">
            <span><?= e($score) ?>%</span>
            <i style="--score: <?= e($score) ?>%"></i>
        </div>
    </td>
    <td><?= e($checked) ?></td>
    <td>
        <div class="row-actions">
            <button type="button" class="icon-btn" data-action="check" title="Jetzt pruefen">&#8635;</button>
            <button type="button" class="icon-btn" data-action="details" title="Details">i</button>
            <button type="button" class="icon-btn" data-action="edit" title="Bearbeiten">&#9998;</button>
            <button type="button" class="icon-btn" data-action="toggle" title="Aktiv umschalten"><?= ((int)$server['enabled'] === 1) ? '&#10074;&#10074;' : '&#9654;' ?></button>
            <button type="button" class="icon-btn danger" data-action="delete" title="Loeschen">&#10005;</button>
        </div>
    </td>
</tr>
