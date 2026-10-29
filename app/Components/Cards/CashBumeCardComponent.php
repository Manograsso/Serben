<?php
namespace SerbenConnect\Components\Cards;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class CashBumeCardComponent extends BaseComponent {
 protected $shortcode='serben_card_cashbume'; protected $title='Card Cash Bume (Net Bume)' ; protected $category='Cards';
 protected $description='Saldo CCI da API, equivalente a Net Bume; distinto de Bumes/Pontos.';
 public function render(Member $member,array $atts=[]):string {return $this->card('NET BUME / CASH BUME',$member->cashBume()->formatted(),'Bonificação de indicações');}
}
