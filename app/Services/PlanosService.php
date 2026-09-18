<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
if(!defined('ABSPATH')){exit;}
class PlanosService{
 private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
 public function listar(int $page=1,int $limit=100):array{return $this->client->integrationGet('clube_beneficio','Planos/listarPlanos',['page'=>max(1,$page),'limit'=>min(100,max(1,$limit))]);}
 public function getPorId(int $id):array{return $this->client->integrationGet('clube_beneficio','Planos/getPlanoPorId',['id_plano'=>$id]);}
 public function cadastrar(array $payload):array{return $this->client->integrationPost('clube_beneficio','Planos/cadastrarPlano',$payload);}
 public function editar(int $id,array $payload):array{$payload['id_plano']=$id;return $this->client->integrationPost('clube_beneficio','Planos/editarPlano',$payload);}
}
