<?php
namespace SerbenConnect\Components\Account;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class DigitalCardComponent extends BaseComponent
{
    protected $shortcode = 'serben_digital_card';
    protected $title = 'Carteirinha digital';
    protected $category = 'Área do associado';
    protected $description = 'Carteirinha completa com foto, dados, status, validade e saldos.';

    public function render(Member $member, array $atts = []): string
    {
        if (!$member->hasClub()) {
            return '<div class="serben-alert serben-warning">Este associado não possui vínculo ativo com esta unidade.</div>';
        }

        $photo = $member->card()->photoUrl();
        $name = $member->profile()->name();
        $cpf = $member->profile()->cpfFormatted();
        $number = $member->card()->number();
        $status = $member->card()->status();
        $expiration = $member->card()->expiration();
        $points = $member->points()->formatted();
        $cashback = $member->cashback()->formatted();
        $store = $member->club()->storeId();

        $html = '<section class="serben-digital-card" aria-label="Carteirinha digital">';
        $html .= '<div class="serben-digital-card__top">';
        if ($photo !== '') {
            $html .= '<img class="serben-digital-card__photo" src="' . esc_url($photo) . '" alt="Foto de ' . esc_attr($name) . '">';
        } else {
            $initial = $name !== '' ? mb_substr($name, 0, 1) : 'S';
            $html .= '<div class="serben-digital-card__photo serben-digital-card__photo--placeholder">' . esc_html($initial) . '</div>';
        }
        $html .= '<div><span class="serben-kicker">Clube Serben</span><h3>' . esc_html($name ?: 'Associado') . '</h3><p>' . esc_html($cpf) . '</p></div>';
        $html .= '<span class="serben-status-badge serben-status-badge--' . esc_attr(strtolower($status) === 'ativo' ? 'active' : 'inactive') . '">' . esc_html($status) . '</span>';
        $html .= '</div>';
        $html .= '<div class="serben-digital-card__number"><span>Número da carteirinha</span><strong>' . esc_html($number) . '</strong></div>';
        $html .= '<div class="serben-digital-card__meta">';
        $html .= '<div><span>Validade</span><strong>' . esc_html($expiration) . '</strong></div>';
        $html .= '<div><span>Pontos</span><strong>' . esc_html($points) . '</strong></div>';
        $html .= '<div><span>Cashback</span><strong>' . esc_html($cashback) . '</strong></div>';
        $html .= '<div><span>Unidade</span><strong>' . esc_html($store ?: '—') . '</strong></div>';
        $html .= '</div></section>';
        return $html;
    }
}
