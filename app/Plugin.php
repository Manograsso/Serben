<?php
namespace SerbenConnect;

use SerbenConnect\Admin\Admin;
use SerbenConnect\Shortcodes\AppShortcode;
use SerbenConnect\Shortcodes\LookupShortcode;
use SerbenConnect\Shortcodes\RegisterShortcode;
use SerbenConnect\Shortcodes\LoginShortcode;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private static $instance = null;

    public static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function activate(): void
    {
        add_option('serben_connect_version', SERBEN_CONNECT_VERSION);
        add_option('serben_connect_settings', [
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
        ]);
    }

    public static function deactivate(): void
    {
        // Mantém configurações e logs para diagnóstico.
    }

    public function boot(): void
    {
        (new Admin())->register();
        (new LookupShortcode())->register();
        (new RegisterShortcode())->register();
        (new LoginShortcode())->register();
        (new AppShortcode())->register();
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        wp_register_style('serben-connect', SERBEN_CONNECT_URL . 'assets/css/serben-connect.css', [], SERBEN_CONNECT_VERSION);
    }
}
