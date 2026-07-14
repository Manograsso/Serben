<?php
namespace SerbenConnect\Providers;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class CacheManager
{
    public function get(string $key)
    {
        return get_transient($this->key($key));
    }

    public function set(string $key, $value, ?int $ttl = null): void
    {
        if ($ttl === null) {
            $ttl = (int) Settings::get('cache_ttl', 600);
        }
        set_transient($this->key($key), $value, max(60, $ttl));
    }

    public function delete(string $key): void
    {
        delete_transient($this->key($key));
    }

    private function key(string $key): string
    {
        return 'serben_' . md5($key);
    }
}
