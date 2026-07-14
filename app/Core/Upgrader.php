<?php
namespace SerbenConnect\Core;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Upgrader
{
    public static function maybeUpgrade(): void
    {
        $installed = (string) get_option('serben_connect_version', '0.0.0');
        if (version_compare($installed, SERBEN_CONNECT_VERSION, '>=')) {
            return;
        }

        // Regrava as configurações com todos os defaults atuais, preservando os
        // valores já informados pelo administrador.
        Settings::update(Settings::all());
        update_option('serben_connect_version', SERBEN_CONNECT_VERSION);
        do_action('serben_connect_upgraded', $installed, SERBEN_CONNECT_VERSION);
    }
}
