<?php
namespace SerbenConnect\Components\Account;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\ContractsProvider;
if (!defined('ABSPATH')) { exit; }
class ContractCardComponent extends BaseComponent
{
    protected $shortcode='serben_contract_card'; protected $title='Card de contrato'; protected $category='Área do associado';
    protected $description='Resumo do contrato e plano atual.';
    public function render(Member $member,array $atts=[]):string
    {
        $items=(new ContractsProvider())->get($member->profile()->cpfRaw())['items']??[];
        $c=$items[0]??[];
        $plan=$c['nome_plano']??$c['nomePlano']??$member->plan()->name();
        $status=$c['situacao_contrato']??$c['status_contrato']??'—';
        $payment=$c['situacao_pagamento']??'—';
        $start=$c['data_ini']??$c['data_inicio']??'—';
        $end=$c['data_fim']??$c['data_final']??'—';
        return '<div class="serben-component-card serben-contract-card"><span class="serben-component-label">Plano atual</span><strong class="serben-component-value">'.esc_html((string)$plan).'</strong><div class="serben-data-grid"><div class="serben-data-item"><span>Contrato</span><strong>'.esc_html((string)$status).'</strong></div><div class="serben-data-item"><span>Pagamento</span><strong>'.esc_html((string)$payment).'</strong></div><div class="serben-data-item"><span>Início</span><strong>'.esc_html((string)$start).'</strong></div><div class="serben-data-item"><span>Fim</span><strong>'.esc_html((string)$end).'</strong></div></div></div>';
    }
}
