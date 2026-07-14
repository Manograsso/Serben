<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnerFiltersComponent extends PublicComponent
{
    protected $shortcode = 'serben_partner_filters';
    protected $title = 'Filtros de parceiros';
    protected $category = 'Clube';
    protected $description = 'Formulário público de busca e filtros para a listagem de parceiros.';

    public function render(Member $member, array $atts = []): string
    {
        $atts = shortcode_atts([
            'show_search' => 'yes',
            'show_category' => 'yes',
            'show_locality' => 'yes',
            'show_benefit_type' => 'yes',
            'show_order' => 'yes',
            'button_text' => 'Filtrar parceiros',
            'clear_text' => 'Limpar filtros',
        ], $atts);

        $action = remove_query_arg([
            'serben_partner_search', 'serben_partner_category', 'serben_partner_locality',
            'serben_partner_benefit_type', 'serben_partner_orderby', 'serben_partner_order',
            'serben_partners_page'
        ]);

        $html = '<form class="serben-partner-filters" method="get" action="' . esc_url($action) . '">';
        $html .= $this->preserveQueryFields();

        if ($this->isYes($atts['show_search'])) {
            $value = isset($_GET['serben_partner_search']) ? sanitize_text_field(wp_unslash($_GET['serben_partner_search'])) : '';
            $html .= '<label><span>Buscar parceiro</span><input type="search" name="serben_partner_search" value="' . esc_attr($value) . '" placeholder="Nome, cidade ou descrição"></label>';
        }
        if ($this->isYes($atts['show_category'])) {
            $html .= $this->taxonomySelect(PartnersProvider::categoryTaxonomy(), 'serben_partner_category', 'Categoria');
        }
        if ($this->isYes($atts['show_locality'])) {
            $html .= $this->taxonomySelect(PartnersProvider::localityTaxonomy(), 'serben_partner_locality', 'Localidade');
        }
        if ($this->isYes($atts['show_benefit_type'])) {
            $html .= $this->taxonomySelect(PartnersProvider::benefitTaxonomy(), 'serben_partner_benefit_type', 'Tipo de benefício');
        }
        if ($this->isYes($atts['show_order'])) {
            $selected = isset($_GET['serben_partner_orderby']) ? sanitize_key(wp_unslash($_GET['serben_partner_orderby'])) : 'title';
            $html .= '<label><span>Ordenar por</span><select name="serben_partner_orderby">';
            foreach (['title' => 'Nome', 'featured' => 'Destaques', 'date' => 'Mais recentes', 'cashback' => 'Maior cashback'] as $value => $label) {
                $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
            }
            $html .= '</select></label>';
            $html .= '<input type="hidden" name="serben_partner_order" value="' . esc_attr(in_array($selected, ['featured', 'date', 'cashback'], true) ? 'DESC' : 'ASC') . '">';
        }

        $html .= '<div class="serben-partner-filters__actions"><button type="submit">' . esc_html((string) $atts['button_text']) . '</button>';
        $html .= '<a href="' . esc_url($action) . '">' . esc_html((string) $atts['clear_text']) . '</a></div></form>';
        return $html;
    }

    private function taxonomySelect(string $taxonomy, string $name, string $label): string
    {
        $selected = isset($_GET[$name]) ? sanitize_title(wp_unslash($_GET[$name])) : '';

        if ($taxonomy === PartnersProvider::categoryTaxonomy()) {
            $items = (new PartnersProvider())->categories()['items'] ?? [];
            if (!$items) { return ''; }
            $html = '<label><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '"><option value="">Todos</option>';
            foreach ($items as $item) {
                $slug = sanitize_title((string) ($item['slug'] ?? ''));
                if ($slug === '') { continue; }
                $itemLabel = (string) ($item['nome'] ?? $item['name'] ?? $slug);
                $count = (int) ($item['count'] ?? 0);
                $html .= '<option value="' . esc_attr($slug) . '" ' . selected($selected, $slug, false) . '>' . esc_html($itemLabel) . ' (' . esc_html(number_format_i18n($count)) . ')</option>';
            }
            $html .= '</select></label>';
            return $html;
        }

        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
        if (is_wp_error($terms) || !$terms) { return ''; }
        $html = '<label><span>' . esc_html($label) . '</span><select name="' . esc_attr($name) . '"><option value="">Todos</option>';
        foreach ($terms as $term) {
            $html .= '<option value="' . esc_attr($term->slug) . '" ' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . ' (' . esc_html(number_format_i18n($this->publishedPartnerCount((int) $term->term_id, $taxonomy))) . ')</option>';
        }
        $html .= '</select></label>';
        return $html;
    }

    private function publishedPartnerCount(int $termId, string $taxonomy): int
    {
        $objectIds = get_objects_in_term($termId, $taxonomy);
        if (is_wp_error($objectIds) || !$objectIds) {
            return 0;
        }

        $count = 0;
        foreach (array_unique(array_map('intval', $objectIds)) as $postId) {
            $post = get_post($postId);
            if ($post && $post->post_type === PartnersProvider::postType() && $post->post_status === 'publish') {
                $count++;
            }
        }
        return $count;
    }

    private function preserveQueryFields(): string
    {
        $owned = [
            'serben_partner_search', 'serben_partner_category', 'serben_partner_locality',
            'serben_partner_benefit_type', 'serben_partner_orderby', 'serben_partner_order',
            'serben_partners_page'
        ];
        $html = '';
        foreach ($_GET as $key => $value) {
            if (in_array((string) $key, $owned, true) || is_array($value)) { continue; }
            $html .= '<input type="hidden" name="' . esc_attr(sanitize_key((string) $key)) . '" value="' . esc_attr(sanitize_text_field(wp_unslash($value))) . '">';
        }
        return $html;
    }

    private function isYes($value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'yes', 'sim', 'true', 'on'], true);
    }
}
