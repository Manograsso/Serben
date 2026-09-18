<?php
namespace SerbenConnect\Support;

if (!defined('ABSPATH')) { exit; }

class Settings
{
    public static function all(): array
    {
        $defaults = [
            'base_url' => 'https://serben.conectar.site',
            'api_key' => '',
            'identifier' => '',
            'id_loja' => '1',
            'cnpj_empresa' => '',
            'cnpj_credenciador' => '',
            'codigo' => '1',
            'test_cpf' => '',
            'register_page_url' => '',
            'debug' => '1',
            'show_technical_front' => '0',
            'enable_cpf_login' => '1',
            'cache_ttl' => '600',
            'partners_post_type' => 'serben_parceiro',
            'partners_category_taxonomy' => 'serben_categoria',
            'partners_locality_taxonomy' => 'localidade',
            'partners_benefit_taxonomy' => 'tipo-de-beneficio',
            'awin_publisher_id' => '690361',
            'awin_api_token' => '',
            'awin_sync_enabled' => '1',
            'awin_default_cashback_percent' => '0',
            'awin_serben_store_id' => '1',
            'awin_serben_user_id' => '1',
            'awin_transaction_days' => '7',
            'awin_credit_currency' => 'BRL',
        ];
        $saved = get_option('serben_connect_settings', []);
        return wp_parse_args(is_array($saved) ? $saved : [], $defaults);
    }

    public static function get(string $key, $default = '')
    {
        $settings = self::all();
        return $settings[$key] ?? $default;
    }

    public static function update(array $data): void
    {
        $current = self::all();
        $incomingToken = sanitize_text_field((string)($data['awin_api_token'] ?? ''));
        $clean = [
            'base_url' => esc_url_raw(rtrim((string)($data['base_url'] ?? $current['base_url']), '/')),
            'api_key' => sanitize_text_field((string)($data['api_key'] ?? $current['api_key'])),
            'identifier' => sanitize_text_field((string)($data['identifier'] ?? $current['identifier'])),
            'id_loja' => sanitize_text_field((string)($data['id_loja'] ?? $current['id_loja'])),
            'cnpj_empresa' => preg_replace('/\D+/', '', (string)($data['cnpj_empresa'] ?? $current['cnpj_empresa'])),
            'cnpj_credenciador' => preg_replace('/\D+/', '', (string)($data['cnpj_credenciador'] ?? $current['cnpj_credenciador'])),
            'codigo' => sanitize_text_field((string)($data['codigo'] ?? $current['codigo'])),
            'test_cpf' => preg_replace('/\D+/', '', (string)($data['test_cpf'] ?? $current['test_cpf'])),
            'register_page_url' => esc_url_raw((string)($data['register_page_url'] ?? $current['register_page_url'])),
            'debug' => !empty($data['debug']) ? '1' : '0',
            'show_technical_front' => !empty($data['show_technical_front']) ? '1' : '0',
            'enable_cpf_login' => !isset($data['enable_cpf_login']) || !empty($data['enable_cpf_login']) ? '1' : '0',
            'cache_ttl' => max(60, (int)($data['cache_ttl'] ?? $current['cache_ttl'])),
            'partners_post_type' => sanitize_key((string)($data['partners_post_type'] ?? $current['partners_post_type'])),
            'partners_category_taxonomy' => sanitize_key((string)($data['partners_category_taxonomy'] ?? $current['partners_category_taxonomy'])),
            'partners_locality_taxonomy' => sanitize_key((string)($data['partners_locality_taxonomy'] ?? $current['partners_locality_taxonomy'])),
            'partners_benefit_taxonomy' => sanitize_key((string)($data['partners_benefit_taxonomy'] ?? $current['partners_benefit_taxonomy'])),
            'awin_publisher_id' => preg_replace('/\D+/', '', (string)($data['awin_publisher_id'] ?? $current['awin_publisher_id'])),
            'awin_api_token' => $incomingToken !== '' ? $incomingToken : (string)$current['awin_api_token'],
            'awin_sync_enabled' => !empty($data['awin_sync_enabled']) ? '1' : '0',
            'awin_default_cashback_percent' => max(0, (float)str_replace(',', '.', (string)($data['awin_default_cashback_percent'] ?? $current['awin_default_cashback_percent']))),
            'awin_serben_store_id' => max(1, (int)($data['awin_serben_store_id'] ?? $current['awin_serben_store_id'])),
            'awin_serben_user_id' => max(1, (int)($data['awin_serben_user_id'] ?? $current['awin_serben_user_id'])),
            'awin_transaction_days' => min(31, max(1, (int)($data['awin_transaction_days'] ?? $current['awin_transaction_days']))),
            'awin_credit_currency' => strtoupper(sanitize_text_field((string)($data['awin_credit_currency'] ?? $current['awin_credit_currency']))),
        ];
        update_option('serben_connect_settings', $clean);
    }
}
