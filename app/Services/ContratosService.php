<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
if(!defined('ABSPATH')){exit;}
class ContratosService{
 private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
 public function porCpf(string $cpf):array{$cpf=preg_replace('/\D+/','',$cpf);return $this->client->integrationGet('clube_beneficio','Contratos/listarContratosPorContratante',['cpf_contratante'=>$cpf]);}
 public function listar(int $page=1,int $limit=100,array $filters=[]):array{return $this->client->integrationGet('clube_beneficio','Contratos/listarContratos',array_merge(['page'=>max(1,$page),'limit'=>min(100,max(1,$limit))],$filters));}
 public function getPorId(int $id):array{return $this->client->integrationGet('clube_beneficio','Contratos/getContratoPorId',['id_contrato'=>$id]);}
 public function gerar(array $payload):array{return $this->client->integrationPost('clube_beneficio','Contratos/gerarContrato',$payload);}
}
