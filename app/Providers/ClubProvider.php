<?php
namespace SerbenConnect\Providers;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Support\Logger;
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

    public function get(string $document, bool $forceRefresh = false): array
    {
        $document = preg_replace('/\D+/', '', $document);
        $idLoja = (string) Settings::get('id_loja');
        if (!$document) {
            return [
                'data' => [], 'http_code' => 0, 'linked' => false,
                'status_retorno' => null, 'id_loja' => $idLoja,
            ];
        }

        // v3: leitura migrada para Portadores; id_loja não faz parte da nova requisição.
        $key = 'club_v3_' . md5($document);
        if (!$forceRefresh) {
            $cached = $this->cache->get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->clientes->buscarSaldoPorDocumento($document);
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $statusRetorno = array_key_exists('statusRetorno', $body) ? (int) $body['statusRetorno'] : null;
        $data = $this->clientes->extractSaldo($result);

        // A API pode devolver o objeto real na raiz, dentro de `data` ou em uma
        // lista. O vínculo deve considerar tanto o corpo bruto quanto o objeto
        // normalizado que alimenta os componentes.
        $hasClubData = $this->clientes->hasClubData($body)
            || (is_array($data) && $this->clientes->hasClubData($data));
        $cardStatus = strtolower(trim((string) ($data['status_cartao'] ?? '')));
        $linked = $hasClubData || $statusRetorno === 1
            || in_array($cardStatus, ['ativo', 'active', '1'], true);

        $payload = [
            'data' => $data,
            'raw' => $body,
            'http_code' => (int) ($result['code'] ?? 0),
            'linked' => $linked,
            'status_retorno' => $statusRetorno,
            'status_cartao' => $body['status_cartao'] ?? ($data['status_cartao'] ?? null),
            'id_loja' => (string) ($body['idLoja'] ?? ($data['idLoja'] ?? $idLoja)),
            'synced_at' => current_time('mysql'),
        ];

        // Vínculos válidos usam o TTL normal; respostas negativas expiram rapidamente.
        $ttl = $linked ? null : 60;
        $this->cache->set($key, $payload, $ttl);

        Logger::add('info', 'DATA', 'ClubProvider/get', (int) ($result['code'] ?? 0), 'Club data resolved', [
            'document' => $document,
            'id_loja' => $idLoja,
            'linked' => $linked,
            'status_retorno' => $statusRetorno,
            'has_numero_cartao' => !empty($body['numero_cartao'] ?? $data['numero_cartao'] ?? null),
            'has_saldos_portador' => !empty($body['saldos_portador'] ?? $data['saldos_portador'] ?? null),
            'response_keys' => array_slice(array_keys($body), 0, 20),
            'normalized_keys' => array_slice(array_keys(is_array($data) ? $data : []), 0, 20),
        ]);

        return $payload;
    }
}
