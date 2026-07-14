<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;
if (!defined('ABSPATH')) { exit; }
class PlanosService {
    private $client;
    public function __construct(?Client $client=null){$this->client=$client?:new Client();}
    public function listar(): array { return $this->client->get('Planos_clube',['idLoja'=>(string)Settings::get('id_loja')]); }
    public function cadastrar(array $payload): array { return $this->client->post('Planos_clube', $payload); }
}
