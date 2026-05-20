<?php
declare(strict_types=1);

namespace ModernMonitor;

final class EmailNotifier
{
    private ServerRepository $repo;

    public function __construct(ServerRepository $repo)
    {
        $this->repo = $repo;
    }

    public function notifyIfNeeded(array $before, array $after, array $result): bool
    {
        $settings = $this->repo->settings();
        if ($settings['email_enabled'] !== '1' || (int)($after['notify_enabled'] ?? 0) !== 1) {
            return false;
        }

        $current = (string)($after['status'] ?? 'unknown');
        $previous = (string)($before['status'] ?? ($after['_previous_status'] ?? 'unknown'));
        $lastNotified = (string)($before['last_notified_status'] ?? 'unknown');

        $threshold = max(1, (int)($settings['warning_threshold_checks'] ?? 1));
        $downCount = $current === 'down' ? $this->repo->consecutiveStatusCount((int)$after['id'], 'down', $threshold) : 0;

        $isDownAlert = $current === 'down'
            && (int)($after['notify_on_down'] ?? 1) === 1
            && $lastNotified !== 'down'
            && $downCount >= $threshold;

        $isRecoveryAlert = $current === 'up'
            && $previous === 'down'
            && (int)($after['notify_on_recovery'] ?? 1) === 1;

        if (!$isDownAlert && !$isRecoveryAlert) {
            return false;
        }

        $recipient = trim((string)($after['notify_email'] ?? ''));
        if ($recipient === '') {
            $recipient = trim((string)$settings['email_default_to']);
        }
        if ($recipient === '') {
            return false;
        }

        $prefix = trim((string)$settings['email_subject_prefix']);
        $state = $current === 'up' ? 'RECOVERY' : 'DOWN';
        $subject = trim($prefix . ' ' . $state . ': ' . (string)$after['name']);
        $body = implode("\n", [
            'Monitor: ' . (string)$after['name'],
            'Target: ' . (string)$after['url'],
            'Status: ' . strtoupper($current),
            'Response time: ' . ((string)($result['response_time_ms'] ?? '-')) . ' ms',
            'HTTP code: ' . ((string)($result['http_code'] ?? '-')),
            'Checked at: ' . ((string)($result['checked_at'] ?? '-')),
            'Error: ' . ((string)($result['error_message'] ?? '-')),
        ]);

        $from = trim((string)$settings['email_from']);
        $fromName = trim((string)$settings['email_from_name']);
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($from !== '') {
            $headers[] = 'From: ' . ($fromName !== '' ? $fromName . ' <' . $from . '>' : $from);
        }

        $sent = false;
        $error = null;
        try {
            $sent = @mail($recipient, $subject, $body, implode("\r\n", $headers));
            if (!$sent) {
                $error = 'mail() returned false';
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->repo->logNotification((int)$after['id'], $recipient, $subject, $body, $sent, $error);
        if ($sent) {
            $this->repo->markNotified((int)$after['id'], $current);
        }

        return true;
    }
}
