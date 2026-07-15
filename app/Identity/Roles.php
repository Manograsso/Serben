<?php
namespace SerbenConnect\Identity;

if (!defined('ABSPATH')) { exit; }

final class Roles
{
    public static function register(): void
    {
        add_role('serben_associado', 'Associado Serben', ['read' => true]);
        add_role('serben_empresa_cliente', 'Empresa Cliente Serben', ['read' => true]);
        add_role('serben_lojista', 'Lojista Serben', [
            'read' => true,
            'upload_files' => true,
            'edit_serben_partner' => true,
        ]);
    }
}
