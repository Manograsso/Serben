<?php
namespace SerbenConnect\Components\Dependents;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\DependentsProvider;

if (!defined('ABSPATH')) { exit; }

class DependentsComponent extends BaseComponent
{
    protected $shortcode='serben_dependents'; protected $title='Dependentes'; protected $category='Dependentes'; protected $description='Lista os dependentes encontrados nos contratos do associado.';
    public function render(Member $member,array $atts=[]): string
    {
        $data=(new DependentsProvider())->get($member->profile()->cpf());
        if(empty($data['items'])) return '<div class="serben-component-empty">Nenhum dependente encontrado.</div>';
        $html='<div class="serben-dependents-list">';
        foreach($data['items'] as $item){
            $name=$item['nome_dependente']??$item['nome_portador']??$item['nome']??'Dependente';
            $cpf=$item['cpf_dependente']??$item['cpf_portador']??$item['cpf']??'';
            $relation=$item['parentesco']??$item['nome_parentesco']??'';
            $html.='<article class="serben-component-card"><strong>'.esc_html((string)$name).'</strong>';
            if($cpf!=='')$html.='<span>'.esc_html((string)$cpf).'</span>';
            if($relation!=='')$html.='<small>'.esc_html((string)$relation).'</small>';
            $html.='</article>';
        }
        return $html.'</div>';
    }
}
