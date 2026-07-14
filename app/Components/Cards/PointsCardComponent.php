<?php
namespace SerbenConnect\Components\Cards;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PointsCardComponent extends BaseComponent
{
    protected $shortcode = 'serben_card_points';
    protected $title = 'Card de pontos';
    protected $category = 'Cards';
    protected $description = 'Exibe um card visual com o saldo de pontos.';

    public function render(Member $member, array $atts = []): string
    {
        return $member->hasClub() ? $this->card('Pontos', $member->points()->formatted(), 'Saldo atual') : $this->card('Pontos', 'Indisponível', $member->club()->message());
    }
}
