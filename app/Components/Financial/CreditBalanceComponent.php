<?php
namespace SerbenConnect\Components\Financial;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class CreditBalanceComponent extends BaseComponent
{
    protected $shortcode = 'serben_credit_balance';
    protected $title = 'Saldo de crédito';
    protected $category = 'Financeiro';
    protected $description = 'Exibe o saldo de crédito do cartão em reais.';
    public function render(Member $member, array $atts = []): string { return $member->hasClub() ? $this->value($member->financial()->creditFormatted()) : $this->value($member->club()->message()); }
}
