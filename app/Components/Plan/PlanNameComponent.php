<?php
namespace SerbenConnect\Components\Plan;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PlanNameComponent extends BaseComponent
{
    protected $shortcode = 'serben_plan_name';
    protected $title = 'Nome do plano';
    protected $category = 'Plano';
    protected $description = 'Exibe o nome do plano atual do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->plan()->name());
    }
}
