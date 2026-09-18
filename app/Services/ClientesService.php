<?php
namespace SerbenConnect\Services;

use SerbenConnect\API\Client;
use SerbenConnect\Support\Settings;
use SerbenConnect\Services\PortadoresService;

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

        // v1.5.0: official Integration API replacement for Clientes/byDocumento.
        return (new PortadoresService($this->client))->getPorDocumento($cpf);
    }


    public function buscarSaldoPorDocumento(string $cpf): array
    {
        // v1.5.1: leitura de saldo/cartão vem da nova API de Portadores.
        // A API legada fica reservada às operações de fidelidade que ainda não
        // possuem substituta oficial (ex.: crédito de cashback).
        return $this->buscarPorDocumento($cpf);
    }

    public function extractSaldo(array $result): array
    {
        if (empty($result['ok']) || !is_array($result['body'])) {
            return [];
        }

        $body = $result['body'];
        // New Integration API envelope: {status, message, response}.
        if (isset($body['response']) && is_array($body['response']) && !empty($body['response'])) {
            return $this->normalizePortador($body['response']);
        }
        $paths = [
            ['data', 'saldo'],
            ['data', 'saldos'],
            ['data', 'cliente'],
            ['data', 'clientes', 0],
            ['data', 0],
            ['data'],
            [0],
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
        $keys = [
            'saldo_ponto_liberado', 'saldo_pontos_liberado',
            'saldo_cashback_liberado', 'saldo_cashback_disponivel',
            'saldo_pontos', 'saldo_cashback', 'numero_cartao', 'status_cartao',
            'pontos', 'cashback', 'saldos_portador', 'todos_cartoes_aceitos_nessa_loja'
        ];
        foreach ($keys as $key) {
            if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '') {
                return true;
            }
        }

        // Algumas instalações da API envolvem o objeto real em `data`, `cliente`,
        // `saldo` ou em uma lista numérica. Inspeciona recursivamente apenas
        // estruturas conhecidas para não confundir mensagens de status com vínculo.
        foreach (['data', 'cliente', 'saldo', 'saldos'] as $wrapper) {
            if (isset($body[$wrapper]) && is_array($body[$wrapper]) && $this->hasClubData($body[$wrapper])) {
                return true;
            }
        }
        if (isset($body[0]) && is_array($body[0]) && $this->hasClubData($body[0])) {
            return true;
        }

        // statusRetorno só é usado quando nenhum dado material de cartão/saldo existe.
        return isset($body['statusRetorno']) && (int) $body['statusRetorno'] === 1;
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

        // Nova API de integração: { status, message, response }.
        if (isset($body['response']) && is_array($body['response']) && !empty($body['response'])) {
            return $this->normalizePortador($body['response']);
        }

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

    /**
     * Normaliza o retorno da nova API sem perder a estrutura original.
     * O cartão ativo é também projetado na raiz para manter compatibilidade
     * com os Domain objects e shortcodes existentes.
     */
    private function normalizePortador(array $portador): array
    {
        $cards = isset($portador['cartoes']) && is_array($portador['cartoes'])
            ? $portador['cartoes'] : [];

        $active = [];
        foreach ($cards as $card) {
            if (!is_array($card)) { continue; }
            $status = strtolower(trim((string) ($card['status'] ?? '')));
            if ($status === 'ativo' || $status === 'active' || $status === '1') {
                $active = $card;
                break;
            }
        }
        if (!$active && isset($cards[0]) && is_array($cards[0])) {
            $active = $cards[0];
        }

        if ($active) {
            foreach ([
                'numero_cartao',
                'saldo_credito',
                'limite_credito',
                'saldo_cashback_liberado',
                'saldo_pontos_liberado',
            ] as $key) {
                if (array_key_exists($key, $active)) {
                    $portador[$key] = $active[$key];
                }
            }
            if (array_key_exists('status', $active)) {
                $portador['status_cartao'] = $active['status'];
            }
        }

        // Alias transitório para componentes/instalações que ainda conhecem
        // o singular retornado pela API legada.
        if (array_key_exists('saldo_pontos_liberado', $portador)
            && !array_key_exists('saldo_ponto_liberado', $portador)) {
            $portador['saldo_ponto_liberado'] = $portador['saldo_pontos_liberado'];
        }

        return $portador;
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
