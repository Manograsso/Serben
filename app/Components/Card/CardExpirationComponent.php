<?php
namespace SerbenConnect\Components\Card;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class CardExpirationComponent extends BaseComponent
{
    protected $shortcode = 'serben_card_expiration';
    protected $title = 'Validade da carteirinha';
    protected $category = 'Carteirinha';
    protected $description = 'Exibe a validade do cartão no formato MM/AAAA.';
    public function render(Member $member, array $atts = []): string { return $member->hasClub() ? $this->value($member->card()->expiration()) : $this->value($member->club()->message()); }
}
