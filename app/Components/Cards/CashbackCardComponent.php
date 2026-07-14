<?php
namespace SerbenConnect\Components\Cards;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class CashbackCardComponent extends BaseComponent
{
    protected $shortcode = 'serben_card_cashback';
    protected $title = 'Card de cashback';
    protected $category = 'Cards';
    protected $description = 'Exibe um card visual com o saldo de cashback.';

    public function render(Member $member, array $atts = []): string
    {
        return $member->hasClub() ? $this->card('Cashback', $member->cashback()->formatted(), 'Saldo disponível') : $this->card('Cashback', 'Indisponível', $member->club()->message());
    }
}
