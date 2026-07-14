<?php
namespace SerbenConnect\Components\Contracts;
use SerbenConnect\Components\BaseComponent; use SerbenConnect\Domain\Member; use SerbenConnect\Providers\ContractsProvider;
if(!defined('ABSPATH'))exit;
class ContractsComponent extends BaseComponent{
 protected $shortcode='serben_contracts';protected $title='Contratos';protected $category='Contratos';protected $description='Lista contratos do associado.';
 public function render(Member $m,array $atts=[]):string{$cpf=$m->profile()->cpfRaw();$items=(new ContractsProvider())->get($cpf)['items']??[];if(!$items)return'<span class="serben-component-empty">Nenhum contrato encontrado.</span>';$h='<div class="serben-list">';foreach($items as$c){$name=$c['nome_plano']??$c['nomePlano']??'Contrato';$status=$c['situacao_contrato']??$c['status_contrato']??$c['status']??'—';$start=$c['data_ini']??$c['data_inicio']??'';$end=$c['data_fim']??$c['data_final']??'';$h.='<article class="serben-list-card"><strong>'.esc_html($name).'</strong><span>'.esc_html((string)$status).'</span>';if($start||$end)$h.='<small>'.esc_html(trim($start.' — '.$end,' —')).'</small>';$h.='</article>';}$h.='</div>';return$h;}
}
