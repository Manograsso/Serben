<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) {
    exit;
}

class Plan
{
    private $cliente;
    private $balance;

    public function __construct(array $cliente, array $balance = [])
    {
        $this->cliente = $cliente;
        $this->balance = $balance;
    }

    public function name(): string
    {
        $contract = $this->firstContract();
        return (string) DataExtractor::first([$contract, $this->balance, $this->cliente], ['nome_plano', 'plano.nome', 'nomePlano', 'plano', 'plan_name'], '—');
    }

    public function status(): string
    {
        $contract = $this->firstContract();
        return (string) DataExtractor::first([$contract, $this->balance, $this->cliente], ['situacao_contrato', 'status_contrato', 'status', 'plano.status'], '—');
    }

    private function firstContract(): array
    {
        $records = DataExtractor::get($this->cliente, 'contratos_portador.registros', []);
        if (is_array($records) && isset($records[0]) && is_array($records[0])) {
            return $records[0];
        }
        $records = DataExtractor::get($this->balance, 'contratos_portador.registros', []);
        if (is_array($records) && isset($records[0]) && is_array($records[0])) {
            return $records[0];
        }
        return [];
    }
}
