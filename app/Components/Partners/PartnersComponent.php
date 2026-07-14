<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Domain\Partner;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnersComponent extends PublicComponent
{
    protected $shortcode = 'serben_partners';
    protected $title = 'Empresas parceiras';
    protected $category = 'Clube';
    protected $description = 'Lista parceiros publicados no CPT de parceiros configurado com filtros e paginação.';

    public function render(Member $member, array $atts = []): string
    {
        $atts = shortcode_atts([
            'category' => '',
            'locality' => '',
            'benefit_type' => '',
            'city' => '',
            'state' => '',
            'featured' => '',
            'cashback' => '',
            'discount' => '',
            'accepts_bume' => '',
            'search' => '',
            'limit' => '12',
            'orderby' => 'title',
            'order' => 'ASC',
            'pagination' => 'no',
            'query_filters' => 'yes',
            'show_count' => 'yes',
            'empty_text' => 'Nenhum parceiro encontrado.',
        ], $atts);

        if ($this->isYes($atts['query_filters'])) {
            $atts = array_merge($atts, $this->queryFilters());
        }

        $page = isset($_GET['serben_partners_page']) ? max(1, absint(wp_unslash($_GET['serben_partners_page']))) : 1;
        $atts['paged'] = $page;

        $result = (new PartnersProvider())->all($atts);
        $items = $result['items'] ?? [];
        $total = (int) ($result['total'] ?? 0);
        $pages = (int) ($result['pages'] ?? 0);

        $html = '<div class="serben-partners-results" data-serben-partners-results>';
        if ($this->isYes($atts['show_count'])) {
            $html .= '<div class="serben-partners-summary"><strong>' . esc_html(number_format_i18n($total)) . '</strong> ' . esc_html($total === 1 ? 'parceiro encontrado' : 'parceiros encontrados') . '</div>';
        }

        if (!$items) {
            $html .= '<span class="serben-component-empty">' . esc_html((string) $atts['empty_text']) . '</span></div>';
            return $html;
        }

        $html .= '<div class="serben-partners-grid">';
        foreach ($items as $partner) {
            if (!$partner instanceof Partner) { continue; }
            $html .= $this->partnerCard($partner);
        }
        $html .= '</div>';

        if ($this->isYes($atts['pagination']) && $pages > 1) {
            $html .= $this->pagination($page, $pages);
        }

        $html .= '</div>';
        return $html;
    }

    private function queryFilters(): array
    {
        $map = [
            'search' => 'serben_partner_search',
            'category' => 'serben_partner_category',
            'locality' => 'serben_partner_locality',
            'benefit_type' => 'serben_partner_benefit_type',
            'city' => 'serben_partner_city',
            'state' => 'serben_partner_state',
            'featured' => 'serben_partner_featured',
            'cashback' => 'serben_partner_cashback',
            'discount' => 'serben_partner_discount',
            'orderby' => 'serben_partner_orderby',
            'order' => 'serben_partner_order',
        ];
        $filters = [];
        foreach ($map as $key => $queryKey) {
            if (!isset($_GET[$queryKey])) { continue; }
            $value = sanitize_text_field(wp_unslash($_GET[$queryKey]));
            if ($value !== '') { $filters[$key] = $value; }
        }
        return $filters;
    }

    private function pagination(int $current, int $totalPages): string
    {
        $baseUrl = remove_query_arg('serben_partners_page');
        $links = paginate_links([
            'base' => add_query_arg('serben_partners_page', '%#%', $baseUrl),
            'format' => '',
            'current' => $current,
            'total' => $totalPages,
            'type' => 'array',
            'prev_text' => '‹ Anterior',
            'next_text' => 'Próxima ›',
        ]);
        if (!$links) { return ''; }
        return '<nav class="serben-partners-pagination" aria-label="Paginação de parceiros">' . implode('', $links) . '</nav>';
    }

    private function isYes($value): bool
    {
        return in_array(strtolower((string) $value), ['1', 'yes', 'sim', 'true', 'on'], true);
    }

    private function partnerCard(Partner $partner): string
    {
        $logo = $partner->logoUrl();
        $cover = (string) $partner->get('foto_capa', '');
        $image = $logo ?: $cover;
        $url = (string) $partner->get('permalink', '');
        $html = '<article class="serben-partner-card">';
        if ($image) {
            $html .= '<a href="' . esc_url($url) . '"><img class="serben-partner-card__logo" src="' . esc_url($image) . '" alt="' . esc_attr($partner->name()) . '" loading="lazy"></a>';
        }
        $html .= '<div class="serben-partner-card__body"><h3><a href="' . esc_url($url) . '">' . esc_html($partner->name()) . '</a></h3>';
        $location = trim((string) $partner->get('cidade') . ((string) $partner->get('estado') ? ' / ' . (string) $partner->get('estado') : ''));
        if ($location) { $html .= '<span class="serben-partner-card__location">' . esc_html($location) . '</span>'; }
        if ($partner->shortDescription()) { $html .= '<p>' . esc_html($partner->shortDescription()) . '</p>'; }
        $badges = [];
        if ($partner->isFeatured()) { $badges[] = 'Destaque'; }
        if ($partner->hasCashback()) {
            $percent = (float) $partner->get('cashback_associado', 0);
            $badges[] = $percent > 0 ? 'Cashback ' . rtrim(rtrim(number_format_i18n($percent, 2), '0'), ',') . '%' : 'Cashback';
        }
        if ($partner->hasDiscount()) { $badges[] = 'Desconto'; }
        if ($badges) {
            $html .= '<div class="serben-partner-badges">';
            foreach ($badges as $badge) { $html .= '<span>' . esc_html($badge) . '</span>'; }
            $html .= '</div>';
        }
        $html .= '<a class="serben-partner-card__link" href="' . esc_url($url) . '">Ver parceiro</a></div></article>';
        return $html;
    }
}
