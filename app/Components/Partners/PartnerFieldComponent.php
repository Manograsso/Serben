<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Domain\Partner;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnerFieldComponent extends PublicComponent
{
    protected $shortcode = 'serben_partner_field';
    protected $title = 'Campo do parceiro';
    protected $category = 'Clube';

    public function render(Member $member, array $atts = []): string
    {
        $atts = shortcode_atts(['field' => 'nome_fantasia', 'id' => '0', 'current' => 'yes', 'format' => 'text'], $atts);
        $provider = new PartnersProvider();
        $partner = (int) $atts['id'] > 0 ? $provider->find((int) $atts['id']) : $provider->current();
        if (!$partner instanceof Partner) { return ''; }
        $value = $partner->get(sanitize_key((string) $atts['field']));
        if (is_bool($value)) { return $value ? 'Sim' : 'Não'; }
        if (is_array($value)) {
            $value = array_map(static function ($item) {
                if (is_array($item)) { return $item['name'] ?? $item['slug'] ?? $item['url'] ?? ''; }
                return (string) $item;
            }, $value);
            return esc_html(implode(', ', array_filter($value)));
        }
        $value = (string) $value;
        if ($atts['format'] === 'url' && $value !== '') { return esc_url($value); }
        if ($atts['format'] === 'html') { return wp_kses_post($value); }
        if ($atts['format'] === 'money') { return esc_html(wp_strip_all_tags(number_format_i18n((float) $value, 2))); }
        if ($atts['format'] === 'percent') { return esc_html(number_format_i18n((float) $value, 2) . '%'); }
        return esc_html($value);
    }
}
