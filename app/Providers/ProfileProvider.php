<?php
namespace SerbenConnect\Providers;

use SerbenConnect\Services\ClientesService;

if (!defined('ABSPATH')) { exit; }

class ProfileProvider
{
    private $clientes;
    private $cache;

    public function __construct(?ClientesService $clientes = null, ?CacheManager $cache = null)
    {
        $this->clientes = $clientes ?: new ClientesService();
        $this->cache = $cache ?: new CacheManager();
    }

    public function get(string $cpf, bool $forceRefresh = false): array
    {
        $cpf = preg_replace('/\D+/', '', $cpf);
        if (!$cpf) {
            return ['data' => [], 'http_code' => 0, 'found' => false];
        }

        $key = 'profile_' . md5($cpf);
        if (!$forceRefresh) {
            $cached = $this->cache->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->clientes->buscarPorDocumento($cpf);
        $data = $this->clientes->extractCliente($result) ?: [];
        $payload = [
            'data' => $data,
            'http_code' => (int) ($result['code'] ?? 0),
            'found' => !empty($data),
            'synced_at' => current_time('mysql'),
        ];
        $this->cache->set($key, $payload);
        return $payload;
    }
}
