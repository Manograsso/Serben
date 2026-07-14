<?php
namespace SerbenConnect\Components\Account;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class ProfileCardComponent extends BaseComponent
{
    protected $shortcode='serben_profile_card'; protected $title='Card de perfil'; protected $category='Área do associado';
    protected $description='Resumo dos dados pessoais do associado.';
    public function render(Member $member,array $atts=[]):string
    {
        $p=$member->profile();
        return '<div class="serben-profile-card"><h3>'.esc_html($p->name()).'</h3><div class="serben-data-grid">'
            .'<div class="serben-data-item"><span>CPF</span><strong>'.esc_html($p->cpfFormatted()).'</strong></div>'
            .'<div class="serben-data-item"><span>E-mail</span><strong>'.esc_html($p->email()).'</strong></div>'
            .'<div class="serben-data-item"><span>Celular</span><strong>'.esc_html($p->phone()).'</strong></div>'
            .'<div class="serben-data-item"><span>Última sincronização</span><strong>'.esc_html((string)get_user_meta(get_current_user_id(),'serben_last_sync',true)).'</strong></div>'
            .'</div></div>';
    }
}
