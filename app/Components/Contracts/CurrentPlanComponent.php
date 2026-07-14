<?php
namespace SerbenConnect\Components\Contracts;
use SerbenConnect\Components\BaseComponent;use SerbenConnect\Domain\Member;use SerbenConnect\Providers\ContractsProvider;
if(!defined('ABSPATH'))exit;
class CurrentPlanComponent extends BaseComponent{protected $shortcode='serben_current_plan';protected $title='Plano atual';protected $category='Contratos';public function render(Member$m,array$a=[]):string{$i=(new ContractsProvider())->get($m->profile()->cpfRaw())['items']??[];$c=$i[0]??[];return$this->value($c['nome_plano']??$c['nomePlano']??$m->plan()->name());}}
