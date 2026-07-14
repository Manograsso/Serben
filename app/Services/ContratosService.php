<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;
if (!defined('ABSPATH')) { exit; }
class ContratosService {
    private $client;
    public function __construct(?Client $client=null){$this->client=$client?:new Client();}
    public function porCpf(string $cpf): array {
        $cpf=preg_replace('/\D+/','',$cpf); $idLoja=(string)Settings::get('id_loja');
        return $this->client->get('Contratos_clube/contratante/'.$cpf,['idLoja'=>$idLoja]);
    }
}
