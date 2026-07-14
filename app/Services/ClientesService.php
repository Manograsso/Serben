<?php
namespace SerbenConnect\Services;

use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class ClientesService
{
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client();
    }

    public function buscarPorDocumento(string $cpf): array
    {
        $cpf = preg_replace('/\D+/', '', $cpf);
        if (!$cpf) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'raw' => 'CPF vazio.', 'url' => ''];
        }

        return $this->client->get('Clientes/byDocumento', [
            'documento' => $cpf,
        ], true);
    }


    public function buscarSaldoPorDocumento(string $cpf): array
    {
        $cpf = preg_replace('/\D+/', '', $cpf);
        $idLoja = Settings::get('id_loja');

        if (!$cpf) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'raw' => 'CPF vazio.', 'url' => ''];
        }

        $query = ['documento' => $cpf];
        if ($idLoja !== '') {
            $query['idLoja'] = $idLoja;
        }

        return $this->client->get('Clientes/byDocumento', $query, true);
    }

    public function extractSaldo(array $result): array
    {
        if (empty($result['ok']) || !is_array($result['body'])) {
            return [];
        }

        $body = $result['body'];
        $paths = [
            ['data', 'saldo'],
            ['data', 'saldos'],
            ['data', 'cliente'],
            ['data', 'clientes', 0],
            ['data', 0],
            ['data'],
            ['saldo'],
            ['saldos'],
        ];

        foreach ($paths as $path) {
            $value = $body;
            foreach ($path as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    $value = null;
                    break;
                }
            }
            if (is_array($value) && !empty($value)) {
                return $value;
            }
        }

        return is_array($body) ? $body : [];
    }

    public function hasClubData(array $body): bool
    {
        if (isset($body['statusRetorno'])) {
            return (int) $body['statusRetorno'] === 1;
        }
        $keys = ['saldo_pontos', 'saldo_cashback', 'numero_cartao', 'status_cartao', 'pontos', 'cashback', 'saldos_portador', 'todos_cartoes_aceitos_nessa_loja'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Tentativa opcional para dados de portador. Algumas chaves/IDENTIFIER podem não ter permissão.
     */
    public function buscarPortadorOpcional(string $cpf): array
    {
        $cpf = preg_replace('/\D+/', '', $cpf);
        if (!$cpf) {
            return ['ok' => false, 'code' => 0, 'body' => null, 'raw' => 'CPF vazio.', 'url' => ''];
        }

        return $this->client->get('Portador_clube', [
            'pesquisa_portador' => $cpf,
        ], false);
    }

    public function cadastrar(array $data): array
    {
        $settings = Settings::all();
        $payload = [
            'cpf_cnpj' => preg_replace('/\D+/', '', (string)($data['cpf_cnpj'] ?? '')),
            'nome' => sanitize_text_field((string)($data['nome'] ?? '')),
            'rg' => sanitize_text_field((string)($data['rg'] ?? '')),
            'sexo' => strtoupper(sanitize_text_field((string)($data['sexo'] ?? ''))),
            'data_nasc' => sanitize_text_field((string)($data['data_nasc'] ?? '')),
            'fone' => preg_replace('/\D+/', '', (string)($data['fone'] ?? '')),
            'celular' => preg_replace('/\D+/', '', (string)($data['celular'] ?? '')),
            'email' => sanitize_email((string)($data['email'] ?? '')),
            'estado' => strtoupper(sanitize_text_field((string)($data['estado'] ?? ''))),
            'cidade' => sanitize_text_field((string)($data['cidade'] ?? '')),
            'bairro' => sanitize_text_field((string)($data['bairro'] ?? '')),
            'cep' => preg_replace('/\D+/', '', (string)($data['cep'] ?? '')),
            'endereco' => sanitize_text_field((string)($data['endereco'] ?? '')),
            'numero' => sanitize_text_field((string)($data['numero'] ?? '')),
            'complemento' => sanitize_text_field((string)($data['complemento'] ?? '')),
            'cnpj_empresa' => preg_replace('/\D+/', '', (string)($settings['cnpj_empresa'] ?? '')),
            'codigo' => sanitize_text_field((string)($settings['codigo'] ?? '1')),
        ];

        $cnpjCorporacao = preg_replace('/\D+/', '', (string)($data['cnpj_corporacao'] ?? ''));
        if ($cnpjCorporacao) {
            $payload['cnpj_corporacao'] = $cnpjCorporacao;
        }

        return $this->client->post('Clientes', $payload, true);
    }

    public function extractCliente(array $result): ?array
    {
        if (empty($result['ok']) || !is_array($result['body'])) {
            return null;
        }

        $body = $result['body'];
        $paths = [
            ['data', 'dados_portador', 0],
            ['data', 'cliente'],
            ['data', 'clientes', 0],
            ['data', 0],
            ['dados_portador', 0],
            ['cliente'],
            ['clientes', 0],
            ['data'],
        ];

        foreach ($paths as $path) {
            $value = $body;
            foreach ($path as $key) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    $value = $value[$key];
                } else {
                    $value = null;
                    break;
                }
            }
            if (is_array($value) && !empty($value)) {
                return $value;
            }
        }

        // Se a própria raiz já parecer um cliente.
        $knownKeys = ['cpf_cnpj', 'documento', 'cpf', 'nome', 'email', 'nome_portador', 'cpf_portador'];
        foreach ($knownKeys as $key) {
            if (isset($body[$key])) {
                return $body;
            }
        }

        return null;
    }

    public function extractPortador(array $result): ?array
    {
        if (empty($result['ok']) || !is_array($result['body'])) {
            return null;
        }
        $body = $result['body'];
        $p = $body['data']['dados_portador'][0] ?? $body['dados_portador'][0] ?? null;
        return is_array($p) ? $p : null;
    }
}
