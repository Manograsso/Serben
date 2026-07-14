<?php
namespace SerbenConnect\Components\Card;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class CardStatusComponent extends BaseComponent
{
    protected $shortcode = 'serben_card_status';
    protected $title = 'Status da carteirinha';
    protected $category = 'Carteirinha';
    protected $description = 'Exibe o status da carteirinha/cartão.';

    public function render(Member $member, array $atts = []): string
    {
        return $member->hasClub() ? $this->value($member->card()->status()) : $this->value($member->club()->message());
    }
}
