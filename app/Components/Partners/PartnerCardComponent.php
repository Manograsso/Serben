<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Domain\Partner;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnerCardComponent extends PublicComponent
{
    protected $shortcode = 'serben_partner_card';
    protected $title = 'Card individual do parceiro';
    protected $category = 'Clube';

    public function render(Member $member, array $atts = []): string
    {
        $atts = shortcode_atts(['id' => '0'], $atts);
        $provider = new PartnersProvider();
        $partner = (int) $atts['id'] > 0 ? $provider->find((int) $atts['id']) : $provider->current();
        if (!$partner instanceof Partner) { return '<span class="serben-component-empty">Parceiro não encontrado.</span>'; }
        $logo = $partner->logoUrl();
        $html = '<article class="serben-partner-detail">';
        if ($partner->coverUrl()) { $html .= '<img class="serben-partner-detail__cover" src="' . esc_url($partner->coverUrl()) . '" alt="">'; }
        $html .= '<div class="serben-partner-detail__body">';
        if ($logo) { $html .= '<img class="serben-partner-detail__logo" src="' . esc_url($logo) . '" alt="' . esc_attr($partner->name()) . '">'; }
        $html .= '<h2>' . esc_html($partner->name()) . '</h2>';
        if ($partner->fullDescription()) { $html .= '<div class="serben-partner-detail__description">' . wp_kses_post(wpautop($partner->fullDescription())) . '</div>'; }
        $html .= '</div></article>';
        return $html;
    }
}
