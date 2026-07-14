<?php
namespace SerbenConnect\Components\Financial;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PointsComponent extends BaseComponent
{
    protected $shortcode = 'serben_points';
    protected $title = 'Saldo de pontos';
    protected $category = 'Financeiro';
    protected $description = 'Exibe o saldo atual de pontos do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $member->hasClub() ? $this->value($member->points()->formatted()) : $this->value($member->club()->message());
    }
}
