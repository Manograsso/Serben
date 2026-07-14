<?php
namespace SerbenConnect\Providers;
use SerbenConnect\Services\PlanosService;
if (!defined('ABSPATH')) { exit; }
class PlansProvider {
 private $cache,$service; public function __construct(?CacheManager $c=null,?PlanosService $s=null){$this->cache=$c?:new CacheManager();$this->service=$s?:new PlanosService();}
 public function all(bool $force=false): array {$key='plans_all';if(!$force){$v=$this->cache->get($key);if(is_array($v))return $v;}$r=$this->service->listar();$items=$this->normalize($r['body']??[]);$out=['ok'=>$r['ok']??false,'items'=>$items,'raw'=>$r['body']??null];if($out['ok'])$this->cache->set($key,$out,1800);return $out;}
 private function normalize($b):array{if(!is_array($b))return[];foreach(['data','registros','planos','items']as$k)if(isset($b[$k]))return$this->normalize($b[$k]);if(array_is_list($b))return array_values(array_filter($b,'is_array'));return isset($b['id_plano'])||isset($b['nomePlano'])?[$b]:[];}
}
