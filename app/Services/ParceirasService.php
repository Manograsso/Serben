<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
if(!defined('ABSPATH')){exit;}
class ParceirasService{
 private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
 public function listar(?int $categoria=null,int $page=1,int $limit=100):array{$q=['page'=>max(1,$page),'limit'=>min(100,max(1,$limit))];if($categoria)$q['id_categoria']=$categoria;return $this->client->integrationGet('clube_beneficio','Empresas/listarEmpresas',$q);}
 public function getPorId(int $id):array{return $this->client->integrationGet('clube_beneficio','Empresas/getEmpresaPorId',['id'=>$id]);}
 public function categorias():array{return $this->client->integrationGet('clube_beneficio','Empresas/listarCategorias');}
 public function cadastrar(array $payload):array{return $this->client->integrationPost('clube_beneficio','Empresas/cadastrarEmpresa',$payload);}
}
