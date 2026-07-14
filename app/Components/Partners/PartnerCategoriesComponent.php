<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnerCategoriesComponent extends PublicComponent
{
    protected $shortcode = 'serben_partner_categories';
    protected $title = 'Categorias de parceiros';
    protected $category = 'Clube';

    public function render(Member $member, array $atts = []): string
    {
        $items = (new PartnersProvider())->categories()['items'] ?? [];
        if (!$items) { return '<span class="serben-component-empty">Nenhuma categoria encontrada.</span>'; }
        $html = '<ul class="serben-category-list">';
        foreach ($items as $item) {
            $url = get_term_link((int) $item['id'], PartnersProvider::categoryTaxonomy());
            $html .= '<li><a href="' . (is_wp_error($url) ? '#' : esc_url($url)) . '">' . esc_html($item['nome']) . '</a></li>';
        }
        return $html . '</ul>';
    }
}
