<?php
namespace SerbenConnect\Components\Account;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class WalletComponent extends BaseComponent
{
    protected $shortcode='serben_wallet'; protected $title='Carteira'; protected $category='Área do associado';
    protected $description='Resumo de pontos, cashback, crédito e vínculo com a unidade.';
    public function render(Member $member,array $atts=[]):string
    {
        $html='<div class="serben-wallet serben-components-row">';
        $html.=$this->card('Pontos',$member->points()->formatted(),'Saldo de pontos');
        $html.=$this->card('Cashback',$member->cashback()->formatted(),'Saldo disponível');
        $html.=$this->card('Crédito',$member->financial()->creditFormatted(),'Limite de crédito');
        $html.=$this->card('Vínculo',$member->hasClub()?'Ativo':'Não localizado','Unidade '.$member->club()->storeId());
        return $html.'</div>';
    }
}
