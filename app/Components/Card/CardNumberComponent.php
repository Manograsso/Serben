<?php
namespace SerbenConnect\Components\Card;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class CardNumberComponent extends BaseComponent
{
    protected $shortcode = 'serben_card_number';
    protected $title = 'Número da carteirinha';
    protected $category = 'Carteirinha';
    protected $description = 'Exibe o número da carteirinha/cartão do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $member->hasClub() ? $this->value($member->card()->number()) : $this->value($member->club()->message());
    }
}
