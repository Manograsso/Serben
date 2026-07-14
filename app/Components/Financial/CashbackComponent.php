<?php
namespace SerbenConnect\Components\Financial;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class CashbackComponent extends BaseComponent
{
    protected $shortcode = 'serben_cashback';
    protected $title = 'Saldo de cashback';
    protected $category = 'Financeiro';
    protected $description = 'Exibe o saldo atual de cashback do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $member->hasClub() ? $this->value($member->cashback()->formatted()) : $this->value($member->club()->message());
    }
}
