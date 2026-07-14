<?php
namespace SerbenConnect\Components\WooCommerce;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Integrations\WooCommerce\PlanProductSync;

if (!defined('ABSPATH')) {
    exit;
}

class PlanPurchaseButtonComponent extends BaseComponent
{
    protected $shortcode = 'serben_plan_purchase_button';
    protected $title = 'Botão de compra do plano';
    protected $category = 'WooCommerce';
    protected $description = 'Exibe um botão para adicionar ao carrinho o produto vinculado a um plano Serben.';

    public function render(Member $member, array $atts = []): string
    {
        if (!class_exists('WooCommerce')) {
            return '<span class="serben-component-empty">WooCommerce indisponível.</span>';
        }

        $atts = shortcode_atts([
            'id' => '',
            'text' => 'Contratar plano',
            'class' => 'button alt',
        ], $atts, $this->shortcode);

        $planId = sanitize_text_field((string)$atts['id']);
        if ($planId === '') {
            return '<span class="serben-component-empty">Informe o ID do plano.</span>';
        }

        $products = get_posts([
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'meta_key' => PlanProductSync::META_PLAN_ID,
            'meta_value' => $planId,
        ]);

        if (!$products) {
            return '<span class="serben-component-empty">Plano ainda não vinculado a um produto.</span>';
        }

        $url = add_query_arg('add-to-cart', (int)$products[0], wc_get_cart_url());
        return '<a class="' . esc_attr((string)$atts['class']) . ' serben-plan-purchase-button" href="' . esc_url($url) . '">' . esc_html((string)$atts['text']) . '</a>';
    }
}
