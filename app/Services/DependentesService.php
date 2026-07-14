<?php
namespace SerbenConnect\Services;

use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

class DependentesService
{
    private $client;
    public function __construct(?Client $client = null) { $this->client = $client ?: new Client(); }

    public function parentescos(): array
    {
        return $this->client->get('parentesco');
    }

    public function cadastrar(array $data): array
    {
        $data['id_loja'] = (int) Settings::get('id_loja');
        return $this->client->post('dependentes', $data);
    }
}
