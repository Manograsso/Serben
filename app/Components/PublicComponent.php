<?php
namespace SerbenConnect\Components;

use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base para componentes públicos que não dependem de um associado logado.
 *
 * Mantém a mesma assinatura dos componentes existentes para preservar o
 * registro centralizado de shortcodes, mas não consulta o UserDataProvider.
 */
abstract class PublicComponent extends BaseComponent
{
    public function renderShortcode($atts = []): string
    {
        wp_enqueue_style('serben-connect');

        return $this->render(
            new Member([], [], []),
            is_array($atts) ? $atts : []
        );
    }
}
