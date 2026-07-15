<?php
namespace SerbenConnect;

use SerbenConnect\Admin\Admin;
use SerbenConnect\Shortcodes\AppShortcode;
use SerbenConnect\Shortcodes\LookupShortcode;
use SerbenConnect\Shortcodes\RegisterShortcode;
use SerbenConnect\Shortcodes\LoginShortcode;
use SerbenConnect\Shortcodes\RegisterSubmitShortcode;
use SerbenConnect\Registration\RegistrationController;
use SerbenConnect\Components\ComponentRegistry;
use SerbenConnect\Components\ShortcodeRegistry;
use SerbenConnect\Shortcodes\AccountShortcodes;
use SerbenConnect\Actions\RefreshMemberAction;
use SerbenConnect\Integrations\WooCommerce\PlanProductSync;
use SerbenConnect\Shortcodes\DependentSubmitShortcode;
use SerbenConnect\Dependents\RegistrationController as DependentRegistrationController;
use SerbenConnect\Core\Upgrader;
use SerbenConnect\Identity\Roles;
use SerbenConnect\Shortcodes\PartnerLoginShortcode;
use SerbenConnect\Integrations\Elementor\ElementorIntegration;

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
        Roles::register();
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
            'cache_ttl' => '600',
        ]);
    }

    public static function deactivate(): void
    {
        // Mantém configurações e logs para diagnóstico.
    }

    public function boot(): void
    {
        Roles::register();
        Upgrader::maybeUpgrade();
        (new Admin())->register();
        (new LookupShortcode())->register();
        (new RegisterShortcode())->register();
        (new LoginShortcode())->register();
        (new PartnerLoginShortcode())->register();
        (new AppShortcode())->register();
        (new RegisterSubmitShortcode())->register();
        (new RegistrationController())->register();
        (new AccountShortcodes())->register();
        (new RefreshMemberAction())->register();
        (new PlanProductSync())->register();
        (new DependentSubmitShortcode())->register();
        (new DependentRegistrationController())->register();
        (new ElementorIntegration())->register();
        (new ShortcodeRegistry(new ComponentRegistry()))->register();
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        wp_register_style('serben-connect', SERBEN_CONNECT_URL . 'assets/css/serben-connect.css', [], SERBEN_CONNECT_VERSION);
        wp_register_script('serben-register-submit', SERBEN_CONNECT_URL . 'assets/js/serben-register-submit.js', [], SERBEN_CONNECT_VERSION, true);
        wp_register_script('serben-dependent-submit', SERBEN_CONNECT_URL . 'assets/js/serben-dependent-submit.js', [], SERBEN_CONNECT_VERSION, true);
    }
}
