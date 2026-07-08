<?php
namespace SerbenConnect\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Logger
{
    public static function add(string $level, string $method, string $endpoint, int $code, string $message, $context = null): void
    {
        $settings = Settings::all();
        if ($settings['debug'] !== '1' && $level !== 'error') {
            return;
        }

        $logs = get_option('serben_connect_logs', []);
        if (!is_array($logs)) {
            $logs = [];
        }

        $logs[] = [
            'time' => current_time('mysql'),
            'level' => $level,
            'method' => strtoupper($method),
            'endpoint' => $endpoint,
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ];

        if (count($logs) > 250) {
            $logs = array_slice($logs, -250);
        }

        update_option('serben_connect_logs', $logs, false);
    }

    public static function all(): array
    {
        $logs = get_option('serben_connect_logs', []);
        return is_array($logs) ? array_reverse($logs) : [];
    }

    public static function clear(): void
    {
        update_option('serben_connect_logs', [], false);
    }
}
