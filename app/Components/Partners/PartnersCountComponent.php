<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnersCountComponent extends PublicComponent
{
    protected $shortcode = 'serben_partners_count';
    protected $title = 'Contador de parceiros';
    protected $category = 'Clube';
    protected $description = 'Exibe a quantidade de parceiros, respeitando os filtros da URL.';

    public function render(Member $member, array $atts = []): string
    {
        $filters = [
            'limit' => -1,
            'search' => isset($_GET['serben_partner_search']) ? sanitize_text_field(wp_unslash($_GET['serben_partner_search'])) : '',
            'category' => isset($_GET['serben_partner_category']) ? sanitize_title(wp_unslash($_GET['serben_partner_category'])) : '',
            'locality' => isset($_GET['serben_partner_locality']) ? sanitize_title(wp_unslash($_GET['serben_partner_locality'])) : '',
            'benefit_type' => isset($_GET['serben_partner_benefit_type']) ? sanitize_title(wp_unslash($_GET['serben_partner_benefit_type'])) : '',
        ];
        $result = (new PartnersProvider())->all($filters);
        $total = (int) ($result['total'] ?? 0);
        return '<span class="serben-partners-count"><strong>' . esc_html(number_format_i18n($total)) . '</strong> ' . esc_html($total === 1 ? 'parceiro' : 'parceiros') . '</span>';
    }
}
