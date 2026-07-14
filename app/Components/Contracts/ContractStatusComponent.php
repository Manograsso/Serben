<?php
namespace SerbenConnect\Components\Contracts;
use SerbenConnect\Components\BaseComponent;use SerbenConnect\Domain\Member;use SerbenConnect\Providers\ContractsProvider;
if(!defined('ABSPATH'))exit;
class ContractStatusComponent extends BaseComponent{protected $shortcode='serben_contract_status';protected $title='Status do contrato';protected $category='Contratos';public function render(Member$m,array$a=[]):string{$i=(new ContractsProvider())->get($m->profile()->cpfRaw())['items']??[];$c=$i[0]??[];return$this->value($c['situacao_contrato']??$c['status_contrato']??$c['status']??'—');}}
