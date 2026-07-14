<?php
namespace SerbenConnect\API;

use SerbenConnect\Support\Logger;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Client
{
    public function get(string $endpoint, array $query = [], bool $sendIdentifier = true): array
    {
        return $this->request('GET', $endpoint, $query, [], $sendIdentifier);
    }

    public function post(string $endpoint, array $body = [], bool $sendIdentifier = true): array
    {
        return $this->request('POST', $endpoint, [], $body, $sendIdentifier);
    }

    public function request(string $method, string $endpoint, array $query = [], array $body = [], bool $sendIdentifier = true): array
    {
        $settings = Settings::all();
        $base = rtrim((string)($settings['base_url'] ?? ''), '/');
        $endpoint = ltrim($endpoint, '/');
        $url = $base . '/api/index.php/api/' . $endpoint;

        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }

        $headers = [
            'x-api-key' => (string)($settings['api_key'] ?? ''),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($sendIdentifier) {
            $headers['IDENTIFIER'] = (string)($settings['identifier'] ?? '');
            $headers['identifier'] = (string)($settings['identifier'] ?? '');
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 30,
            'headers' => $headers,
        ];

        if (strtoupper($method) !== 'GET') {
            $args['body'] = wp_json_encode($body);
        }

        $start = microtime(true);
        $response = wp_remote_request($url, $args);
        $duration = round((microtime(true) - $start) * 1000);

        $safeHeaders = $headers;
        if (!empty($safeHeaders['x-api-key'])) {
            $safeHeaders['x-api-key'] = '***';
        }
        if (!empty($safeHeaders['IDENTIFIER'])) {
            $safeHeaders['IDENTIFIER'] = '***';
        }
        if (!empty($safeHeaders['identifier'])) {
            $safeHeaders['identifier'] = '***';
        }

        $context = [
            'url' => $url,
            'headers' => $safeHeaders,
            'duration_ms' => $duration,
        ];

        if (is_wp_error($response)) {
            Logger::add('error', $method, $endpoint . $this->queryString($query), 0, $response->get_error_message(), $context);
            return [
                'ok' => false,
                'code' => 0,
                'body' => null,
                'raw' => $response->get_error_message(),
                'url' => $url,
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        $bodyDecoded = json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
        $ok = $code >= 200 && $code < 300;

        Logger::add($ok ? 'info' : 'error', $method, $endpoint . $this->queryString($query), $code, 'API request', $context + [
            'response' => is_string($bodyDecoded) ? mb_substr($bodyDecoded, 0, 2000) : $bodyDecoded,
        ]);

        return [
            'ok' => $ok,
            'code' => $code,
            'body' => $bodyDecoded,
            'raw' => $raw,
            'url' => $url,
        ];
    }

    private function queryString(array $query): string
    {
        return empty($query) ? '' : '?' . http_build_query($query);
    }
}
