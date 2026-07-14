<?php
namespace SerbenConnect\Components\Account;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class PlanCardComponent extends BaseComponent
{
    protected $shortcode='serben_plan_card'; protected $title='Card de plano'; protected $category='Área do associado';
    protected $description='Card visual com o plano e status do associado.';
    public function render(Member $member,array $atts=[]):string
    { return $this->card('Plano',$member->plan()->name(),$member->plan()->status()); }
}
