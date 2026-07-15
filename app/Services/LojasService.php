<?php
namespace SerbenConnect\Services;

use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class LojasService
{
    private $client;
    public function __construct(?Client $client = null) { $this->client = $client ?: new Client(); }

    public function listarDoCredenciador(): array
    {
        $cnpj = preg_replace('/\D+/', '', (string) Settings::get('cnpj_credenciador'));
        if (!$cnpj) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'raw' => 'CNPJ do credenciador não configurado.', 'url' => ''];
        }
        return $this->client->get('Lojas', ['cnpj_credenciador' => $cnpj], true);
    }

    public function buscarPorCnpj(string $cnpj): ?array
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj);
        $result = $this->listarDoCredenciador();
        if (empty($result['ok']) || !is_array($result['body'])) { return null; }
        foreach ($this->flattenStores($result['body']) as $store) {
            $candidate = preg_replace('/\D+/', '', (string)($store['cnpj'] ?? $store['cnpj_loja'] ?? $store['cpf_cnpj'] ?? $store['documento'] ?? ''));
            if ($candidate === $cnpj) { return $store; }
        }
        return null;
    }

    private function flattenStores(array $body): array
    {
        foreach (['data','lojas','items','resultados'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $body = $body[$key]; break;
            }
        }
        if (isset($body[0]) && is_array($body[0])) { return $body; }
        return [$body];
    }
}
