<?php
namespace SerbenConnect\Components\Cards;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PlanCardComponent extends BaseComponent
{
    protected $shortcode = 'serben_card_plan';
    protected $title = 'Card do plano';
    protected $category = 'Cards';
    protected $description = 'Exibe um card visual com plano e status.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->card('Plano', $member->plan()->name(), $member->plan()->status());
    }
}
