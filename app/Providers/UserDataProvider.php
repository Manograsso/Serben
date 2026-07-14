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
        $cpf = preg_replace('/\D+/', '', (string) get_user_meta($userId, 'serben_cpf', true));
        if (!$cpf) {
            return null;
        }

        $profile = $this->profileProvider->get($cpf, $forceRefresh);
        $club = $this->clubProvider->get($cpf, $forceRefresh);
        $cliente = is_array($profile['data'] ?? null) ? $profile['data'] : [];

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
            'cpf' => $cpf,
            'profile_http' => $profile['http_code'] ?? null,
            'profile_found' => $profile['found'] ?? false,
            'club_http' => $club['http_code'] ?? null,
            'club_linked' => $club['linked'] ?? false,
            'club_status_retorno' => $club['status_retorno'] ?? null,
        ]);

        self::$current = new Member($cliente, is_array($club['data'] ?? null) ? $club['data'] : [], $club);
        return self::$current;
    }

    public function clearCurrent(): void
    {
        self::$current = null;
        if (!is_user_logged_in()) { return; }
        $cpf = preg_replace('/\D+/', '', (string) get_user_meta(get_current_user_id(), 'serben_cpf', true));
        if ($cpf) {
            $this->cache->delete('profile_' . md5($cpf));
            $idLoja = (string) \SerbenConnect\Support\Settings::get('id_loja');
            $this->cache->delete('club_' . md5($cpf . '|' . $idLoja));
        }
    }
}
