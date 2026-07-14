<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;
if (!defined('ABSPATH')) { exit; }
class ParceirasService {
    private $client;
    public function __construct(?Client $client=null){$this->client=$client?:new Client();}
    public function listar(?int $categoria=null): array {
        $q=['idLoja'=>(string)Settings::get('id_loja')]; if($categoria){$q['idCategoria']=$categoria;}
        return $this->client->get('Parceiras',$q);
    }
    public function categorias(): array { return $this->client->get('Parceiras/categorias',['idLoja'=>(string)Settings::get('id_loja')]); }
}
