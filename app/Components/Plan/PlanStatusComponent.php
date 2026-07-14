<?php
namespace SerbenConnect\Components\Plan;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PlanStatusComponent extends BaseComponent
{
    protected $shortcode = 'serben_plan_status';
    protected $title = 'Status do plano';
    protected $category = 'Plano';
    protected $description = 'Exibe o status do plano ou contrato.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->plan()->status());
    }
}
