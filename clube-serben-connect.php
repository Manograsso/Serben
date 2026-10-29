<?php
/**
 * Plugin Name: Clube Serben Connect
 * Plugin URI: https://github.com/Manograsso/Serben
 * Description: Integração entre WordPress e API Clube Serben.
 * Version: 2.1.3
 * Author: Pedro Manograsso
 * License: GPL-2.0-or-later
 * Text Domain: clube-serben-connect
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// O plugin suporta PHP 7.4; alguns providers usam array_is_list (PHP 8.1+).
if (!function_exists('array_is_list')) {
    function array_is_list(array $items): bool {
        $index = 0;
        foreach ($items as $key => $_value) {
            if ($key !== $index++) { return false; }
        }
        return true;
    }
}

define('SERBEN_CONNECT_VERSION', '2.1.3');
define('SERBEN_CONNECT_FILE', __FILE__);
define('SERBEN_CONNECT_PATH', plugin_dir_path(__FILE__));
define('SERBEN_CONNECT_URL', plugin_dir_url(__FILE__));

require_once SERBEN_CONNECT_PATH . 'autoload.php';

register_activation_hook(__FILE__, ['SerbenConnect\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['SerbenConnect\\Plugin', 'deactivate']);

add_action('plugins_loaded', function () {
    SerbenConnect\Plugin::instance()->boot();
});
