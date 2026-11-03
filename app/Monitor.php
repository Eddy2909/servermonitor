<?php
declare(strict_types=1);

namespace ModernMonitor;

final class Monitor
{
    public function check(array $server): array
    {
        if ($server['type'] === 'ping') {
            return $this->checkPing($server);
        }

        return $server['type'] === 'tcp'
            ? $this->checkTcp($server)
            : $this->checkWebsite($server);
    }

    private function checkWebsite(array $server): array
    {
        $started = microtime(true);
        $timeout = (int)($server['timeout_seconds'] ?? 10);
        $url = (string)$server['url'];
        $method = (string)($server['method'] ?? 'GET');
        $body = '';
        $httpCode = null;
        $error = null;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'Server Monitor',
                CURLOPT_NOSIGNAL => true,
                CURLOPT_HEADER => false,
            ];
            if (defined('CURLOPT_PROTOCOLS_STR')) {
                $options[CURLOPT_PROTOCOLS_STR] = 'http,https';
                $options[CURLOPT_REDIR_PROTOCOLS_STR] = 'http,https';
            } else {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
                $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
            }
            curl_setopt_array($curl, $options);
            if ($method === 'HEAD') {
                curl_setopt($curl, CURLOPT_NOBODY, true);
            }
            $body = (string)curl_exec($curl);
            $httpCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            if ($curlError !== '') {
                $error = $curlError;
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => "User-Agent: Server Monitor\r\n",
                ],
            ]);
            $body = (string)@file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            $httpCode = $this->statusFromHeaders($headers);
            if ($body === '' && $httpCode === null) {
                $error = 'Request failed.';
            }
        }

        $latency = (int)round((microtime(true) - $started) * 1000);
        $status = 'up';

        if ($httpCode === null || !$this->statusAllowed((int)$httpCode, (string)$server['expected_status'])) {
            $status = 'down';
            $error = $error ?: 'Unexpected HTTP status ' . ($httpCode ?? 'none') . '.';
        }

        $expectedText = (string)($server['expected_text'] ?? '');
        if ($status === 'up' && $expectedText !== '' && strpos($body, $expectedText) === false) {
            $status = 'down';
            $error = 'Expected text was not found.';
        }

        return $this->result($status, $latency, $httpCode, $error);
    }

    private function checkTcp(array $server): array
    {
        $started = microtime(true);
        $timeout = (int)($server['timeout_seconds'] ?? 10);
        $host = preg_replace('#^[a-z]+://#i', '', (string)$server['url']);
        $host = trim((string)$host, '/');
        $port = (int)($server['port'] ?? 0);

        if ($host === '' || $port <= 0) {
            return $this->result('down', 0, null, 'TCP host and port are required.');
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $latency = (int)round((microtime(true) - $started) * 1000);

        if (is_resource($socket)) {
            fclose($socket);
            return $this->result('up', $latency, null, null);
        }

        return $this->result('down', $latency, null, $errstr !== '' ? $errstr : 'Connection failed.');
    }

    private function checkPing(array $server): array
    {
        $started = microtime(true);
        $timeout = max(1, min(10, (int)($server['timeout_seconds'] ?? 5)));
        $host = $this->hostFromTarget((string)$server['url']);

        if ($host === '') {
            return $this->result('down', 0, null, 'Ping host is required.');
        }

        if (function_exists('exec')) {
            $isWindows = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN';
            $command = $isWindows
                ? 'ping -n 1 -w ' . ($timeout * 1000) . ' ' . escapeshellarg($host)
                : 'ping -c 1 -W ' . $timeout . ' ' . escapeshellarg($host);
            $output = [];
            $exitCode = 1;
            @exec($command, $output, $exitCode);
            $latency = (int)round((microtime(true) - $started) * 1000);
            if ($exitCode === 0) {
                return $this->result('up', $latency, null, null);
            }
        }

        // Kein stiller TCP-80-Ersatz mehr: ein TCP-Check wird nur genutzt,
        // wenn ein Port konfiguriert wurde, sonst ehrlicher Fehler statt Falsch-Positiv.
        $port = (int)($server['port'] ?? 0);
        if ($port <= 0) {
            $latency = (int)round((microtime(true) - $started) * 1000);
            return $this->result('down', $latency, null, 'ICMP-Ping nicht verfuegbar (exec deaktiviert); bitte TCP-Port setzen.');
        }

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        $latency = (int)round((microtime(true) - $started) * 1000);
        if (is_resource($socket)) {
            fclose($socket);
            return $this->result('up', $latency, null, null);
        }

        return $this->result('down', $latency, null, $errstr !== '' ? $errstr : 'Ping failed.');
    }

    private function hostFromTarget(string $target): string
    {
        $host = parse_url($target, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }

        $target = preg_replace('#^[a-z]+://#i', '', $target);
        $target = trim((string)$target, '/');
        return explode('/', $target)[0] ?? '';
    }

    private function result(string $status, int $latency, ?int $httpCode, ?string $error): array
    {
        return [
            'status' => $status,
            'response_time_ms' => $latency,
            'http_code' => $httpCode,
            'error_message' => $error,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function statusAllowed(int $code, string $rule): bool
    {
        $rule = trim($rule) !== '' ? $rule : '200-399';
        foreach (explode(',', $rule) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (str_contains($part, '-')) {
                [$min, $max] = array_map('intval', explode('-', $part, 2));
                if ($code >= $min && $code <= $max) {
                    return true;
                }
                continue;
            }
            if ($code === (int)$part) {
                return true;
            }
        }

        return false;
    }

    private function statusFromHeaders(array $headers): ?int
    {
        // Letzte Statuszeile nehmen: nach Redirects zaehlt die finale Antwort,
        // nicht der erste 3xx-Header.
        $status = null;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string)$header, $matches)) {
                $status = (int)$matches[1];
            }
        }

        return $status;
    }
}
