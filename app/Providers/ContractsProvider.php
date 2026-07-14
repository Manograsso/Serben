<?php
namespace SerbenConnect\Providers;
use SerbenConnect\Services\ContratosService;
if (!defined('ABSPATH')) { exit; }
class ContractsProvider {
    private $cache,$service;
    public function __construct(?CacheManager $cache=null,?ContratosService $service=null){$this->cache=$cache?:new CacheManager();$this->service=$service?:new ContratosService();}
    public function get(string $cpf,bool $force=false): array {
        $key='contracts_'.md5($cpf); if(!$force){$v=$this->cache->get($key);if(is_array($v))return $v;}
        $r=$this->service->porCpf($cpf); $items=$this->normalize($r['body']??[]); $out=['ok'=>$r['ok']??false,'http_code'=>$r['code']??0,'items'=>$items,'raw'=>$r['body']??null];
        if($out['ok'])$this->cache->set($key,$out,300); return $out;
    }
    private function normalize($body): array {
        if(!is_array($body))return [];
        foreach(['data','registros','contratos','contratos_portador','items'] as $k){if(isset($body[$k])){if($k==='contratos_portador'&&is_array($body[$k]))return $this->normalize($body[$k]); return $this->normalize($body[$k]);}}
        if(array_is_list($body))return array_values(array_filter($body,'is_array'));
        foreach($body as $v){if(is_array($v)&&array_is_list($v))return array_values(array_filter($v,'is_array'));}
        return isset($body['id_contrato'])||isset($body['idContrato'])?[$body]:[];
    }
}
