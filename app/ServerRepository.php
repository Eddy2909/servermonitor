<?php
declare(strict_types=1);

namespace ModernMonitor;

use InvalidArgumentException;

final class ServerRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('servers') . ' ORDER BY name ASC'
        );
    }

    public function enabled(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('servers') . ' WHERE enabled = 1 ORDER BY name ASC'
        );
    }

    public function publicServers(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('servers') . '
             WHERE enabled = 1 AND public_visible = 1
             ORDER BY name ASC'
        );
    }

    public function publicPages(): array
    {
        return $this->db->fetchAll(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM ' . $this->db->table('public_page_servers') . ' ps WHERE ps.page_id = p.id) AS assigned_count,
                    (SELECT GROUP_CONCAT(ps.server_id ORDER BY ps.sort_order ASC, ps.server_id ASC) FROM ' . $this->db->table('public_page_servers') . ' ps WHERE ps.page_id = p.id) AS server_ids
             FROM ' . $this->db->table('public_pages') . ' p
             ORDER BY p.title ASC'
        );
    }

    public function publicPageByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{24,80}$/', $token)) {
            return null;
        }

        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('public_pages') . '
             WHERE token = :token AND enabled = 1',
            ['token' => $token]
        );
    }

    public function publicPageServers(int $pageId): array
    {
        return $this->db->fetchAll(
            'SELECT s.*
             FROM ' . $this->db->table('public_page_servers') . ' ps
             JOIN ' . $this->db->table('servers') . ' s ON s.id = ps.server_id
             WHERE ps.page_id = :page_id AND s.enabled = 1
             ORDER BY ps.sort_order ASC, s.name ASC',
            ['page_id' => $pageId]
        );
    }

    public function savePublicPage(array $input): array
    {
        $data = $this->normalizePublicPage($input);

        if (!empty($input['id'])) {
            $data['id'] = (int)$input['id'];
            $this->db->execute(
                'UPDATE ' . $this->db->table('public_pages') . '
                 SET title = :title, badge = :badge, description = :description, theme = :theme,
                     accent = :accent, enabled = :enabled, show_latency = :show_latency,
                     show_uptime = :show_uptime, show_last_check = :show_last_check,
                     show_incidents = :show_incidents, footer_note = :footer_note, updated_at = NOW()
                 WHERE id = :id',
                $data
            );
            $pageId = (int)$data['id'];
        } else {
            $data['token'] = $this->uniquePublicToken();
            $this->db->execute(
                'INSERT INTO ' . $this->db->table('public_pages') . '
                 (token, title, badge, description, theme, accent, enabled, show_latency, show_uptime,
                  show_last_check, show_incidents, footer_note, created_at, updated_at)
                 VALUES (:token, :title, :badge, :description, :theme, :accent, :enabled, :show_latency,
                  :show_uptime, :show_last_check, :show_incidents, :footer_note, NOW(), NOW())',
                $data
            );
            $pageId = $this->db->lastInsertId();
        }

        $this->syncPublicPageServers($pageId, $input['server_ids'] ?? []);
        return $this->publicPage((int)$pageId) ?? [];
    }

    public function deletePublicPage(int $id): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('public_pages') . ' WHERE id = :id', ['id' => $id]);
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('servers') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function save(array $input): array
    {
        $data = $this->normalize($input);

        if (!empty($input['id'])) {
            $data['id'] = (int)$input['id'];
            $this->db->execute(
                'UPDATE ' . $this->db->table('servers') . '
                 SET name = :name, url = :url, type = :type, port = :port, method = :method,
                     expected_status = :expected_status, expected_text = :expected_text,
                     timeout_seconds = :timeout_seconds, enabled = :enabled, public_visible = :public_visible,
                     notify_enabled = :notify_enabled, notify_email = :notify_email,
                     notify_on_down = :notify_on_down, notify_on_recovery = :notify_on_recovery,
                     updated_at = NOW()
                 WHERE id = :id',
                $data
            );
            return $this->find((int)$data['id']) ?? [];
        }

        $this->db->execute(
            'INSERT INTO ' . $this->db->table('servers') . '
             (name, url, type, port, method, expected_status, expected_text, timeout_seconds, enabled,
              public_visible, notify_enabled, notify_email, notify_on_down, notify_on_recovery, created_at, updated_at)
             VALUES (:name, :url, :type, :port, :method, :expected_status, :expected_text, :timeout_seconds, :enabled,
              :public_visible, :notify_enabled, :notify_email, :notify_on_down, :notify_on_recovery, NOW(), NOW())',
            $data
        );

        return $this->find($this->db->lastInsertId()) ?? [];
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM ' . $this->db->table('checks') . ' WHERE server_id = :id', ['id' => $id]);
        $this->db->execute('DELETE FROM ' . $this->db->table('servers') . ' WHERE id = :id', ['id' => $id]);
    }

    public function toggle(int $id): array
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('servers') . ' SET enabled = IF(enabled = 1, 0, 1), updated_at = NOW() WHERE id = :id',
            ['id' => $id]
        );
        return $this->find($id) ?? [];
    }

    public function recordCheck(int $serverId, array $result): array
    {
        $previous = $this->find($serverId);
        $this->db->execute(
            'INSERT INTO ' . $this->db->table('checks') . '
             (server_id, status, response_time_ms, http_code, error_message, checked_at)
             VALUES (:server_id, :status, :response_time_ms, :http_code, :error_message, :checked_at)',
            [
                'server_id' => $serverId,
                'status' => $result['status'],
                'response_time_ms' => $result['response_time_ms'],
                'http_code' => $result['http_code'],
                'error_message' => $result['error_message'],
                'checked_at' => $result['checked_at'],
            ]
        );

        $score = $this->uptimeScore($serverId);
        $this->db->execute(
            'UPDATE ' . $this->db->table('servers') . '
             SET status = :status, response_time_ms = :response_time_ms, http_code = :http_code,
                 uptime_score = :uptime_score, last_error = :last_error, last_checked_at = :last_checked_at,
                 updated_at = NOW()
             WHERE id = :id',
            [
                'status' => $result['status'],
                'response_time_ms' => $result['response_time_ms'],
                'http_code' => $result['http_code'],
                'uptime_score' => $score,
                'last_error' => $result['error_message'],
                'last_checked_at' => $result['checked_at'],
                'id' => $serverId,
            ]
        );

        $server = $this->find($serverId) ?? [];
        $server['_previous_status'] = $previous['status'] ?? 'unknown';
        return $server;
    }

    public function stats(): array
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = "up"), 0) AS online,
                    COALESCE(SUM(status = "down"), 0) AS offline,
                    ROUND(AVG(NULLIF(response_time_ms, 0))) AS avg_latency,
                    ROUND(AVG(uptime_score), 1) AS avg_uptime
             FROM ' . $this->db->table('servers')
        ) ?? [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'online' => (int)($row['online'] ?? 0),
            'offline' => (int)($row['offline'] ?? 0),
            'avg_latency' => (int)($row['avg_latency'] ?? 0),
            'avg_uptime' => (float)($row['avg_uptime'] ?? 0),
        ];
    }

    public function publicStats(): array
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(status = "up"), 0) AS online,
                    COALESCE(SUM(status = "down"), 0) AS offline,
                    ROUND(AVG(NULLIF(response_time_ms, 0))) AS avg_latency,
                    ROUND(AVG(uptime_score), 1) AS avg_uptime
             FROM ' . $this->db->table('servers') . '
             WHERE enabled = 1 AND public_visible = 1'
        ) ?? [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'online' => (int)($row['online'] ?? 0),
            'offline' => (int)($row['offline'] ?? 0),
            'avg_latency' => (int)($row['avg_latency'] ?? 0),
            'avg_uptime' => (float)($row['avg_uptime'] ?? 0),
        ];
    }

    public function publicPageStats(int $pageId): array
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(s.status = "up"), 0) AS online,
                    COALESCE(SUM(s.status = "down"), 0) AS offline,
                    ROUND(AVG(NULLIF(s.response_time_ms, 0))) AS avg_latency,
                    ROUND(AVG(s.uptime_score), 1) AS avg_uptime
             FROM ' . $this->db->table('public_page_servers') . ' ps
             JOIN ' . $this->db->table('servers') . ' s ON s.id = ps.server_id
             WHERE ps.page_id = :page_id AND s.enabled = 1',
            ['page_id' => $pageId]
        ) ?? [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'online' => (int)($row['online'] ?? 0),
            'offline' => (int)($row['offline'] ?? 0),
            'avg_latency' => (int)($row['avg_latency'] ?? 0),
            'avg_uptime' => (float)($row['avg_uptime'] ?? 0),
        ];
    }

    public function recentChecks(int $limit = 16): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, s.name
             FROM ' . $this->db->table('checks') . ' c
             JOIN ' . $this->db->table('servers') . ' s ON s.id = c.server_id
             ORDER BY c.checked_at DESC, c.id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
    }

    public function activityChecks(int $limit = 250): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, s.name, s.url, s.type
             FROM ' . $this->db->table('checks') . ' c
             JOIN ' . $this->db->table('servers') . ' s ON s.id = c.server_id
             ORDER BY c.checked_at DESC, c.id DESC
             LIMIT ' . max(1, min(500, $limit))
        );
    }

    public function publicRecentChecks(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, s.name
             FROM ' . $this->db->table('checks') . ' c
             JOIN ' . $this->db->table('servers') . ' s ON s.id = c.server_id
             WHERE s.enabled = 1 AND s.public_visible = 1
             ORDER BY c.checked_at DESC, c.id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
    }

    public function publicPageRecentChecks(int $pageId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, s.name
             FROM ' . $this->db->table('checks') . ' c
             JOIN ' . $this->db->table('servers') . ' s ON s.id = c.server_id
             JOIN ' . $this->db->table('public_page_servers') . ' ps ON ps.server_id = s.id
             WHERE ps.page_id = :page_id AND s.enabled = 1
             ORDER BY c.checked_at DESC, c.id DESC
             LIMIT ' . max(1, min(50, $limit)),
            ['page_id' => $pageId]
        );
    }

    public function serverChecks(int $serverId, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('checks') . '
             WHERE server_id = :server_id
             ORDER BY checked_at DESC, id DESC
             LIMIT ' . max(1, min(100, $limit)),
            ['server_id' => $serverId]
        );
    }

    public function chartData(int $limit = 80): array
    {
        $checks = $this->db->fetchAll(
            'SELECT c.checked_at, c.status, c.response_time_ms, s.name
             FROM ' . $this->db->table('checks') . ' c
             JOIN ' . $this->db->table('servers') . ' s ON s.id = c.server_id
             ORDER BY c.checked_at DESC, c.id DESC
             LIMIT ' . max(10, min(200, $limit))
        );

        return array_reverse($checks);
    }

    public function publicPageChartData(int $pageId, int $limit = 80): array
    {
        $checks = $this->db->fetchAll(
            'SELECT c.checked_at, c.status, c.response_time_ms, s.name
             FROM ' . $this->db->table('checks') . ' c
             JOIN ' . $this->db->table('servers') . ' s ON s.id = c.server_id
             JOIN ' . $this->db->table('public_page_servers') . ' ps ON ps.server_id = s.id
             WHERE ps.page_id = :page_id AND s.enabled = 1
             ORDER BY c.checked_at DESC, c.id DESC
             LIMIT ' . max(10, min(200, $limit)),
            ['page_id' => $pageId]
        );

        return array_reverse($checks);
    }

    public function serverDetails(int $serverId): array
    {
        $server = $this->find($serverId);
        if ($server === null) {
            return [];
        }

        return [
            'server' => $server,
            'checks' => $this->serverChecks($serverId, 40),
            'notifications' => $this->notificationLog($serverId, 10),
        ];
    }

    public function settings(): array
    {
        $defaults = [
            'email_enabled' => '0',
            'email_from' => 'monitor@example.org',
            'email_from_name' => 'PHP Server Monitor',
            'email_default_to' => '',
            'email_subject_prefix' => '[Monitor]',
            'public_status_enabled' => '1',
            'public_status_title' => 'System Status',
            'public_status_description' => 'Live status of monitored services.',
            'public_status_badge' => 'Live infrastructure status',
            'public_status_theme' => 'dark',
            'public_status_accent' => '#5dd6a5',
            'public_show_latency' => '1',
            'public_show_uptime' => '1',
            'public_show_last_check' => '1',
            'public_show_incidents' => '1',
            'public_footer_note' => '',
            'warning_threshold_checks' => '3',
        ];

        $rows = $this->db->fetchAll('SELECT name, value FROM ' . $this->db->table('settings'));
        foreach ($rows as $row) {
            $defaults[(string)$row['name']] = (string)$row['value'];
        }

        return $defaults;
    }

    public function saveSettings(array $input): array
    {
        $allowed = [
            'email_enabled',
            'email_from',
            'email_from_name',
            'email_default_to',
            'email_subject_prefix',
            'public_status_enabled',
            'public_status_title',
            'public_status_description',
            'public_status_badge',
            'public_status_theme',
            'public_status_accent',
            'public_show_latency',
            'public_show_uptime',
            'public_show_last_check',
            'public_show_incidents',
            'public_footer_note',
            'warning_threshold_checks',
        ];

        foreach ($allowed as $key) {
            $value = (string)($input[$key] ?? '');
            if (in_array($key, ['email_enabled', 'public_status_enabled', 'public_show_latency', 'public_show_uptime', 'public_show_last_check', 'public_show_incidents'], true)) {
                $value = !empty($input[$key]) ? '1' : '0';
            }
            if ($key === 'warning_threshold_checks') {
                $value = (string)max(1, min(20, (int)$value));
            }
            if ($key === 'public_status_theme' && !in_array($value, ['dark', 'light'], true)) {
                $value = 'dark';
            }
            if ($key === 'public_status_accent' && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $value = '#5dd6a5';
            }
            $this->setSetting($key, $value);
        }

        return $this->settings();
    }

    public function notificationLog(?int $serverId = null, int $limit = 20): array
    {
        if ($serverId !== null) {
            return $this->db->fetchAll(
                'SELECT n.*, s.name
                 FROM ' . $this->db->table('notifications') . ' n
                 LEFT JOIN ' . $this->db->table('servers') . ' s ON s.id = n.server_id
                 WHERE n.server_id = :server_id
                 ORDER BY n.created_at DESC, n.id DESC
                 LIMIT ' . max(1, min(50, $limit)),
                ['server_id' => $serverId]
            );
        }

        return $this->db->fetchAll(
            'SELECT n.*, s.name
             FROM ' . $this->db->table('notifications') . ' n
             LEFT JOIN ' . $this->db->table('servers') . ' s ON s.id = n.server_id
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT ' . max(1, min(50, $limit))
        );
    }

    public function consecutiveStatusCount(int $serverId, string $status, int $limit = 20): int
    {
        $rows = $this->db->fetchAll(
            'SELECT status FROM ' . $this->db->table('checks') . '
             WHERE server_id = :server_id
             ORDER BY checked_at DESC, id DESC
             LIMIT ' . max(1, min(50, $limit)),
            ['server_id' => $serverId]
        );

        $count = 0;
        foreach ($rows as $row) {
            if ((string)$row['status'] !== $status) {
                break;
            }
            $count++;
        }

        return $count;
    }

    public function logNotification(?int $serverId, string $recipient, string $subject, string $body, bool $sent, ?string $error): void
    {
        $this->db->execute(
            'INSERT INTO ' . $this->db->table('notifications') . '
             (server_id, recipient, subject, body, status, error_message, created_at)
             VALUES (:server_id, :recipient, :subject, :body, :status, :error_message, NOW())',
            [
                'server_id' => $serverId,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'status' => $sent ? 'sent' : 'failed',
                'error_message' => $error,
            ]
        );
    }

    public function markNotified(int $serverId, string $status): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('servers') . ' SET last_notified_status = :status WHERE id = :id',
            ['status' => $status, 'id' => $serverId]
        );
    }

    public function acquireCronLock(int $seconds): bool
    {
        $lock = $this->setting('cron_running_until');
        if ($lock !== null && (int)$lock > time()) {
            return false;
        }

        $this->setSetting('cron_running_until', (string)(time() + max(30, $seconds)));
        return true;
    }

    public function releaseCronLock(): void
    {
        $this->setSetting('cron_running_until', '0');
    }

    private function uptimeScore(int $serverId): float
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS total, COALESCE(SUM(status = "up"), 0) AS up_checks
             FROM (
                SELECT status FROM ' . $this->db->table('checks') . '
                WHERE server_id = :server_id
                ORDER BY checked_at DESC, id DESC
                LIMIT 100
             ) recent',
            ['server_id' => $serverId]
        ) ?? ['total' => 0, 'up_checks' => 0];

        $total = (int)$row['total'];
        if ($total === 0) {
            return 0.0;
        }

        return round(((int)$row['up_checks'] / $total) * 100, 1);
    }

    private function setting(string $name): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT value FROM ' . $this->db->table('settings') . ' WHERE name = :name',
            ['name' => $name]
        );

        return $row['value'] ?? null;
    }

    private function setSetting(string $name, string $value): void
    {
        $this->db->execute(
            'INSERT INTO ' . $this->db->table('settings') . ' (name, value)
             VALUES (:name, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            ['name' => $name, 'value' => $value]
        );
    }

    private function publicPage(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM ' . $this->db->table('public_page_servers') . ' ps WHERE ps.page_id = p.id) AS assigned_count,
                    (SELECT GROUP_CONCAT(ps.server_id ORDER BY ps.sort_order ASC, ps.server_id ASC) FROM ' . $this->db->table('public_page_servers') . ' ps WHERE ps.page_id = p.id) AS server_ids
             FROM ' . $this->db->table('public_pages') . ' p
             WHERE p.id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    private function normalizePublicPage(array $input): array
    {
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title is required.');
        }

        $theme = (string)($input['theme'] ?? 'dark');
        if (!in_array($theme, ['dark', 'light'], true)) {
            $theme = 'dark';
        }

        $accent = trim((string)($input['accent'] ?? '#5dd6a5'));
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#5dd6a5';
        }

        return [
            'title' => $title,
            'badge' => trim((string)($input['badge'] ?? '')),
            'description' => trim((string)($input['description'] ?? '')),
            'theme' => $theme,
            'accent' => $accent,
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'show_latency' => !empty($input['show_latency']) ? 1 : 0,
            'show_uptime' => !empty($input['show_uptime']) ? 1 : 0,
            'show_last_check' => !empty($input['show_last_check']) ? 1 : 0,
            'show_incidents' => !empty($input['show_incidents']) ? 1 : 0,
            'footer_note' => trim((string)($input['footer_note'] ?? '')),
        ];
    }

    private function syncPublicPageServers(int $pageId, mixed $serverIds): void
    {
        if (!is_array($serverIds)) {
            $serverIds = $serverIds === '' ? [] : [$serverIds];
        }

        $ids = [];
        foreach ($serverIds as $serverId) {
            $serverId = (int)$serverId;
            if ($serverId > 0) {
                $ids[$serverId] = $serverId;
            }
        }

        $this->db->execute(
            'DELETE FROM ' . $this->db->table('public_page_servers') . ' WHERE page_id = :page_id',
            ['page_id' => $pageId]
        );

        $index = 0;
        foreach ($ids as $serverId) {
            $this->db->execute(
                'INSERT INTO ' . $this->db->table('public_page_servers') . ' (page_id, server_id, sort_order)
                 VALUES (:page_id, :server_id, :sort_order)',
                ['page_id' => $pageId, 'server_id' => $serverId, 'sort_order' => $index]
            );
            $index++;
        }
    }

    private function uniquePublicToken(): string
    {
        do {
            $token = bin2hex(random_bytes(18));
            $row = $this->db->fetchOne(
                'SELECT id FROM ' . $this->db->table('public_pages') . ' WHERE token = :token',
                ['token' => $token]
            );
        } while ($row !== null);

        return $token;
    }

    private function normalize(array $input): array
    {
        $type = (string)($input['type'] ?? 'website');
        if (!in_array($type, ['website', 'tcp', 'ping'], true)) {
            throw new InvalidArgumentException('Unknown server type.');
        }

        $name = trim((string)($input['name'] ?? ''));
        $url = trim((string)($input['url'] ?? ''));
        if ($name === '' || $url === '') {
            throw new InvalidArgumentException('Name and target are required.');
        }

        $method = strtoupper((string)($input['method'] ?? 'GET'));
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            $method = 'GET';
        }

        $port = $input['port'] ?? null;
        $port = $port === '' || $port === null ? null : max(1, min(65535, (int)$port));
        if ($type !== 'tcp') {
            $port = null;
        }

        return [
            'name' => $name,
            'url' => $url,
            'type' => $type,
            'port' => $port,
            'method' => $method,
            'expected_status' => trim((string)($input['expected_status'] ?? '200-399')),
            'expected_text' => trim((string)($input['expected_text'] ?? '')),
            'timeout_seconds' => max(1, min(60, (int)($input['timeout_seconds'] ?? 10))),
            'enabled' => !empty($input['enabled']) ? 1 : 0,
            'public_visible' => !empty($input['public_visible']) ? 1 : 0,
            'notify_enabled' => !empty($input['notify_enabled']) ? 1 : 0,
            'notify_email' => trim((string)($input['notify_email'] ?? '')) ?: null,
            'notify_on_down' => !empty($input['notify_on_down']) ? 1 : 0,
            'notify_on_recovery' => !empty($input['notify_on_recovery']) ? 1 : 0,
        ];
    }
}
