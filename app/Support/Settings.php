<?php
namespace SerbenConnect\Support;

if (!defined('ABSPATH')) {
    exit;
}

class Settings
{
    public static function all(): array
    {
        $defaults = [
            'base_url' => 'https://serben.conectar.site',
            'api_key' => '',
            'identifier' => '',
            'id_loja' => '',
            'cnpj_empresa' => '',
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
        $clean = [
            'base_url' => esc_url_raw(rtrim((string)($data['base_url'] ?? ''), '/')),
            'api_key' => sanitize_text_field((string)($data['api_key'] ?? '')),
            'identifier' => sanitize_text_field((string)($data['identifier'] ?? '')),
            'id_loja' => sanitize_text_field((string)($data['id_loja'] ?? '')),
            'cnpj_empresa' => preg_replace('/\D+/', '', (string)($data['cnpj_empresa'] ?? '')),
            'codigo' => sanitize_text_field((string)($data['codigo'] ?? '1')),
            'test_cpf' => preg_replace('/\D+/', '', (string)($data['test_cpf'] ?? '')),
            'register_page_url' => esc_url_raw((string)($data['register_page_url'] ?? '')),
            'debug' => !empty($data['debug']) ? '1' : '0',
            'show_technical_front' => !empty($data['show_technical_front']) ? '1' : '0',
            'enable_cpf_login' => !isset($data['enable_cpf_login']) || !empty($data['enable_cpf_login']) ? '1' : '0',
            'cache_ttl' => max(60, (int)($data['cache_ttl'] ?? 600)),
            'partners_post_type' => sanitize_key((string)($data['partners_post_type'] ?? 'serben_parceiro')),
            'partners_category_taxonomy' => sanitize_key((string)($data['partners_category_taxonomy'] ?? 'serben_categoria')),
            'partners_locality_taxonomy' => sanitize_key((string)($data['partners_locality_taxonomy'] ?? 'localidade')),
            'partners_benefit_taxonomy' => sanitize_key((string)($data['partners_benefit_taxonomy'] ?? 'tipo-de-beneficio')),
        ];
        update_option('serben_connect_settings', $clean);
    }
}
