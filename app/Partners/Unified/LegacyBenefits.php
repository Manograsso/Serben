<?php
/** Original Clube de Beneficios 3.2.0 bundled as a compatibility module. */
namespace SerbenConnect\Partners\Unified;
if (!defined('ABSPATH')) exit;
final class LegacyBenefits {
    public static function register(): void {
        add_action('plugins_loaded', [__CLASS__, 'boot'], 25);
    }
    public static function boot(): void {
        if (PHP_VERSION_ID < 80000) {
            add_action('admin_notices', static function () { if (current_user_can('manage_options')) echo '<div class="notice notice-warning"><p>O modulo legado de beneficios requer PHP 8.0 ou superior.</p></div>'; });
            return;
        }
        // The standalone plugin must be deactivated before the embedded module can take over.
        if (class_exists('Serben_Plugin', false)) {
            add_action('admin_notices', static function () { if (current_user_can('manage_options')) echo '<div class="notice notice-warning"><p>O Clube SerBen Connect detectou o plugin antigo ativo. O modulo incorporado permanece desativado para evitar conflito. Desative o plugin antigo somente apos testar em homologacao.</p></div>'; });
            return;
        }
        $base = SERBEN_CONNECT_PATH . 'legacy-benefits/';
        foreach ([
            'SERBEN_PLUGIN_VERSION' => '3.2.0',
            'SERBEN_PLUGIN_FILE' => SERBEN_CONNECT_FILE,
            'SERBEN_PLUGIN_DIR' => $base,
            'SERBEN_PLUGIN_URL' => SERBEN_CONNECT_URL . 'legacy-benefits/',
            'SERBEN_POST_TYPE' => 'serben_parceiro',
            'SERBEN_TAXONOMY' => 'serben_categoria',
            'SERBEN_META_PREFIX' => '_serben_',
        ] as $name => $value) { if (!defined($name)) define($name, $value); }
        if (is_file($base . 'vendor/autoload.php')) require_once $base . 'vendor/autoload.php';
        foreach ([
            'utils','activator','post-types','term-meta','branches','awin','meta-boxes',
            'admin','import-excel','import-logos','shortcodes','connect-bridge','ajax',
            'rest','export','cover-studio','media-repair','favorites','credenciados-page','plugin'
        ] as $part) {
            require_once $base . 'includes/class-serben-' . $part . '.php';
        }
        \Serben_Plugin::instance()->init();
        new \Serben_Media_Repair();
    }
}
