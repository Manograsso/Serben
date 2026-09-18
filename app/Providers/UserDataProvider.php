<?php
namespace SerbenConnect\Providers;

use SerbenConnect\Domain\Member;
use SerbenConnect\Support\Logger;

if (!defined('ABSPATH')) { exit; }

class UserDataProvider
{
    private static $current = null;
    private $cache;
    private $profileProvider;
    private $clubProvider;

    public function __construct(?CacheManager $cache = null, ?ProfileProvider $profileProvider = null, ?ClubProvider $clubProvider = null)
    {
        $this->cache = $cache ?: new CacheManager();
        $this->profileProvider = $profileProvider ?: new ProfileProvider();
        $this->clubProvider = $clubProvider ?: new ClubProvider();
    }

    public function current(bool $forceRefresh = false): ?Member
    {
        if (!$forceRefresh && self::$current instanceof Member) {
            return self::$current;
        }
        if (!is_user_logged_in()) {
            return null;
        }

        $userId = get_current_user_id();
        $document = $this->resolveDocument($userId);
        if (!$document) {
            Logger::add('warning', 'DATA', 'UserDataProvider/current', 0, 'Logged user has no Serben document', [
                'user_id' => $userId,
            ]);
            return null;
        }

        // Mantém a chave unificada nas contas antigas criadas apenas com serben_cpf.
        if ((string) get_user_meta($userId, 'serben_documento', true) === '') {
            update_user_meta($userId, 'serben_documento', $document);
        }

        $profile = $this->profileProvider->get($document, $forceRefresh);
        $cliente = is_array($profile['data'] ?? null) ? $profile['data'] : [];

        // v1.5.1: a nova rota de Portadores já devolve cartão e saldos.
        // Reaproveita a mesma resposta para o clube, evitando uma segunda
        // requisição HTTP para o mesmo CPF.
        $cardStatus = strtolower(trim((string) ($cliente['status_cartao'] ?? '')));
        $hasCard = !empty($cliente['numero_cartao']);
        $club = [
            'data' => $cliente,
            'raw' => $cliente,
            'http_code' => (int) ($profile['http_code'] ?? 0),
            'linked' => $hasCard && in_array($cardStatus, ['ativo', 'active', '1'], true),
            'status_retorno' => null,
            'status_cartao' => $cliente['status_cartao'] ?? null,
            'id_loja' => (string) \SerbenConnect\Support\Settings::get('id_loja'),
            'synced_at' => $profile['synced_at'] ?? current_time('mysql'),
            'source' => 'integration_portadores',
        ];

        if (empty($cliente)) {
            $json = get_user_meta($userId, 'serben_cliente_data', true);
            $stored = is_string($json) ? json_decode($json, true) : null;
            $cliente = is_array($stored) ? $stored : [];
        }

        if (!empty($cliente)) {
            update_user_meta($userId, 'serben_cliente_data', wp_json_encode($cliente));
        }
        update_user_meta($userId, 'serben_last_sync', current_time('mysql'));

        Logger::add('info', 'DATA', 'UserDataProvider/current', 200, 'Member built from modular providers', [
            'user_id' => $userId,
            'document' => $document,
            'profile_http' => $profile['http_code'] ?? null,
            'profile_found' => $profile['found'] ?? false,
            'club_http' => $club['http_code'] ?? null,
            'club_linked' => $club['linked'] ?? false,
            'club_status_retorno' => $club['status_retorno'] ?? null,
            'club_has_card' => !empty($club['data']['numero_cartao'] ?? null),
            'club_response_keys' => array_slice(array_keys(is_array($club['raw'] ?? null) ? $club['raw'] : []), 0, 20),
        ]);

        self::$current = new Member($cliente, is_array($club['data'] ?? null) ? $club['data'] : [], $club);
        return self::$current;
    }

    public function clearCurrent(): void
    {
        self::$current = null;
        if (!is_user_logged_in()) { return; }

        $document = $this->resolveDocument(get_current_user_id());
        if (!$document) { return; }

        $this->cache->delete('profile_' . md5($document));
        $idLoja = (string) \SerbenConnect\Support\Settings::get('id_loja');
        $this->cache->delete('club_v3_' . md5($document));
        $this->cache->delete('club_v2_' . md5($document . '|' . $idLoja));
        // Remove também as chaves antigas para instalações atualizadas.
        $this->cache->delete('club_' . md5($document . '|' . $idLoja));
    }

    private function resolveDocument(int $userId): string
    {
        $keys = [
            'serben_documento',
            'serben_cpf',
            'cpf',
            'billing_cpf',
            'billing_cnpj',
        ];

        foreach ($keys as $key) {
            $value = preg_replace('/\D+/', '', (string) get_user_meta($userId, $key, true));
            if (strlen($value) === 11 || strlen($value) === 14) {
                return $value;
            }
        }

        return '';
    }
}
