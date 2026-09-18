<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
if(!defined('ABSPATH')){exit;}
final class PortadoresService{
 private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
 public function getPorDocumento(string $documento):array{$documento=preg_replace('/\D+/','',$documento);if(!$documento)return['ok'=>false,'code'=>0,'body'=>null,'raw'=>'Documento vazio.','url'=>''];return $this->client->integrationGet('portadores','Portadores/getPortadorPorDocumento',['documento'=>$documento]);}
 public function extract(array $result):?array{if(empty($result['ok'])||!is_array($result['body']??null))return null;$b=$result['body'];$r=$b['response']??null;if(is_array($r)&&!empty($r))return $r;return null;}
}
