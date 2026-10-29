<?php
namespace SerbenConnect\Identity;

if (!defined('ABSPATH')) { exit; }

final class Roles
{
    public static function register(): void
    {
        add_role('serben_associado', 'Associado Serben', ['read' => true]);
        add_role('serben_empresa_cliente', 'Empresa Cliente Serben', ['read' => true]);
        add_role('serben_entidade_diretoria', 'Entidade — Diretoria', ['read' => true]);
        add_role('serben_entidade_secretaria', 'Entidade — Secretária', ['read' => true]);
        add_role('serben_entidade_atendente', 'Entidade — Atendente', ['read' => true]);
        add_role('serben_entidade', 'Entidade Emissora Serben', ['read' => true, 'upload_files' => true]);
        add_role('serben_lojista', 'Lojista Serben', [
            'read' => true,
            'upload_files' => true,
            'edit_serben_partner' => true,
        ]);
    }
}
