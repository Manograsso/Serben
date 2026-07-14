<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) {
    exit;
}

class Profile
{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function name(): string
    {
        return (string) DataExtractor::first([$this->data], ['nome_portador', 'nome', 'nome_cliente', 'razao_social', 'nome_fantasia'], '');
    }

    public function firstName(): string
    {
        $name = trim($this->name());
        if ($name === '') {
            return '';
        }
        $parts = preg_split('/\s+/', $name);
        return ucfirst(strtolower((string) ($parts[0] ?? $name)));
    }

    public function cpf(): string
    {
        return preg_replace('/\D+/', '', (string) DataExtractor::first([$this->data], ['cpf_portador', 'cpf_cnpj', 'documento', 'cpf'], ''));
    }

    public function cpfFormatted(): string
    {
        $cpf = $this->cpf();
        if (strlen($cpf) !== 11) {
            return $cpf ?: '—';
        }
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    public function email(): string
    {
        return (string) DataExtractor::first([$this->data], ['email_portador', 'email', 'email_cliente'], '');
    }

    public function phone(): string
    {
        return (string) DataExtractor::first([$this->data], ['celular_portador', 'celular', 'fone', 'telefone'], '');
    }

    public function city(): string
    {
        return (string) DataExtractor::first([$this->data], ['cidade', 'endereco.cidade'], '');
    }

    public function state(): string
    {
        return (string) DataExtractor::first([$this->data], ['estado', 'uf', 'endereco.uf'], '');
    }
}
