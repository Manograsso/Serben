<?php
namespace SerbenConnect\Providers;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

class ClubProvider
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
        $idLoja = (string) Settings::get('id_loja');
        if (!$cpf || $idLoja === '') {
            return [
                'data' => [], 'http_code' => 0, 'linked' => false,
                'status_retorno' => null, 'id_loja' => $idLoja,
            ];
        }

        $key = 'club_' . md5($cpf . '|' . $idLoja);
        if (!$forceRefresh) {
            $cached = $this->cache->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->clientes->buscarSaldoPorDocumento($cpf);
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $statusRetorno = array_key_exists('statusRetorno', $body) ? (int) $body['statusRetorno'] : null;
        $linked = $statusRetorno === null
            ? $this->clientes->hasClubData($body)
            : $statusRetorno === 1;

        $payload = [
            'data' => $this->clientes->extractSaldo($result),
            'raw' => $body,
            'http_code' => (int) ($result['code'] ?? 0),
            'linked' => $linked,
            'status_retorno' => $statusRetorno,
            'status_cartao' => $body['status_cartao'] ?? null,
            'id_loja' => (string) ($body['idLoja'] ?? $idLoja),
            'synced_at' => current_time('mysql'),
        ];
        $this->cache->set($key, $payload);
        return $payload;
    }
}
