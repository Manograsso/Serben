<?php
namespace SerbenConnect\Components\Account;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class QuickActionsComponent extends BaseComponent
{
    protected $shortcode='serben_quick_actions'; protected $title='Ações rápidas'; protected $category='Área do associado';
    protected $description='Links rápidos configuráveis por atributos.';
    public function render(Member $member,array $atts=[]):string
    {
        $a=shortcode_atts(['profile_url'=>'/minha-conta/perfil/','card_url'=>'/minha-conta/carteirinha/','plans_url'=>'/minha-conta/plano/','partners_url'=>'/parceiros/','financial_url'=>'/minha-conta/financeiro/'],$atts,'serben_quick_actions');
        $items=['Meu perfil'=>$a['profile_url'],'Carteirinha'=>$a['card_url'],'Meu plano'=>$a['plans_url'],'Parceiros'=>$a['partners_url'],'Financeiro'=>$a['financial_url']];
        $html='<nav class="serben-quick-actions" aria-label="Ações rápidas">';
        foreach($items as $label=>$url){$html.='<a href="'.esc_url($url).'">'.esc_html($label).'</a>';}
        return $html.'</nav>';
    }
}
