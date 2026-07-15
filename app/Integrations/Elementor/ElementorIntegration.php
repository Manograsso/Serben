<?php
namespace SerbenConnect\Integrations\Elementor;

use SerbenConnect\Integrations\Elementor\Widgets\ComponentWidget;

if (!defined('ABSPATH')) {
    exit;
}

final class ElementorIntegration
{
    public function register(): void
    {
        add_action('elementor/elements/categories_registered', [$this, 'registerCategory']);
        add_action('elementor/widgets/register', [$this, 'registerWidgets']);
    }

    public function registerCategory($elementsManager): void
    {
        $elementsManager->add_category('serben-connect', [
            'title' => 'Serben Connect',
            'icon' => 'fa fa-id-card',
        ]);
    }

    public function registerWidgets($widgetsManager): void
    {
        if (!class_exists('Elementor\\Widget_Base')) {
            return;
        }

        foreach ($this->definitions() as $definition) {
            $widgetsManager->register(new ComponentWidget($definition));
        }
    }

    private function definitions(): array
    {
        return [
            ['name' => 'serben-member-name', 'title' => 'Nome do associado', 'icon' => 'eicon-person', 'component' => 'serben_name', 'group' => 'Associado'],
            ['name' => 'serben-member-points', 'title' => 'Pontos do associado', 'icon' => 'eicon-rating', 'component' => 'serben_points', 'group' => 'Associado'],
            ['name' => 'serben-member-cashback', 'title' => 'Cashback do associado', 'icon' => 'eicon-price-table', 'component' => 'serben_cashback', 'group' => 'Associado'],
            ['name' => 'serben-digital-card', 'title' => 'Carteirinha digital', 'icon' => 'eicon-postcard', 'component' => 'serben_digital_card', 'group' => 'Associado', 'visual' => true],
            ['name' => 'serben-wallet', 'title' => 'Carteira do associado', 'icon' => 'eicon-cart-medium', 'component' => 'serben_wallet', 'group' => 'Associado', 'visual' => true],
            ['name' => 'serben-partner-name', 'title' => 'Nome do parceiro', 'icon' => 'eicon-heading', 'component' => 'serben_partner_field', 'group' => 'Parceiro', 'partner_field' => 'nome_fantasia', 'partner_id' => true],
            ['name' => 'serben-partner-logo', 'title' => 'Logo do parceiro', 'icon' => 'eicon-image', 'component' => 'serben_partner_field', 'group' => 'Parceiro', 'partner_field' => 'logo', 'partner_id' => true, 'image' => true],
            ['name' => 'serben-partner-cashback', 'title' => 'Cashback do parceiro', 'icon' => 'eicon-number-field', 'component' => 'serben_partner_field', 'group' => 'Parceiro', 'partner_field' => 'cashback_associado', 'format' => 'percent', 'partner_id' => true],
            ['name' => 'serben-partner-card', 'title' => 'Card do parceiro', 'icon' => 'eicon-gallery-grid', 'component' => 'serben_partner_card', 'group' => 'Parceiro', 'partner_id' => true, 'visual' => true],
            ['name' => 'serben-partners-grid', 'title' => 'Grade de parceiros', 'icon' => 'eicon-posts-grid', 'component' => 'serben_partners', 'group' => 'Parceiro', 'listing' => true, 'visual' => true],
        ];
    }
}
