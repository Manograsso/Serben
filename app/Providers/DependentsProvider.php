<?php
namespace SerbenConnect\Providers;

if (!defined('ABSPATH')) { exit; }

class DependentsProvider
{
    public function get(string $cpf): array
    {
        $contracts = (new ContractsProvider())->get($cpf);
        $items = $this->extract($contracts['raw'] ?? []);
        return ['ok'=>(bool)($contracts['ok'] ?? false),'items'=>$items,'count'=>count($items),'raw'=>$contracts['raw'] ?? null];
    }

    private function extract($node): array
    {
        $out=[];
        $walk=function($value) use (&$walk,&$out){
            if(!is_array($value)) return;
            $isDependent = isset($value['id_portador_dependente']) || isset($value['cpf_dependente']) || isset($value['nome_dependente']) || (isset($value['is_titular']) && (int)$value['is_titular']===0);
            if($isDependent){
                $id=(string)($value['id_portador_dependente'] ?? $value['id_portador'] ?? md5(wp_json_encode($value)));
                $out[$id]=$value;
            }
            foreach($value as $k=>$v){
                if($k==='dependentes' && is_array($v)){
                    if(isset($v['registros'])) $walk($v['registros']); else $walk($v);
                } elseif(is_array($v)) { $walk($v); }
            }
        };
        $walk($node);
        return array_values($out);
    }
}
