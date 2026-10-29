<?php
namespace SerbenConnect\Components\Financial;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class CashBumeComponent extends BaseComponent {
 protected $shortcode='serben_cashbume'; protected $title='Saldo Cash Bume / Net Bume'; protected $category='Financeiro';
 protected $description='Consulta exclusivamente CCI (Net Bume / Cash Bume), nunca Pontos ou Cashback.';
 public function render(Member $member,array $atts=[]):string {return $this->value($member->cashBume()->formatted());}
}
