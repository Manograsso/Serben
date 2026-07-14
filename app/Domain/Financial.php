<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Financial
{
    private $cliente;
    private $clubData;

    public function __construct(array $cliente, array $clubData = [])
    {
        $this->cliente = $cliente;
        $this->clubData = $clubData;
    }

    public function creditInCents()
    {
        return DataExtractor::first([$this->clubData, $this->cliente], [
            'saldos_portador.credito', 'saldo_credito_cartao', 'saldo_credito',
            'credito', 'saldo.credito'
        ], null);
    }

    public function credit(): ?float
    {
        $value = $this->creditInCents();
        return is_numeric($value) ? ((float) $value / 100) : null;
    }

    public function creditFormatted(): string
    {
        $value = $this->credit();
        return $value === null ? '—' : 'R$ ' . number_format($value, 2, ',', '.');
    }

    public function paymentStatus(): string
    {
        $contract = $this->firstContract();
        return (string) DataExtractor::first([$contract, $this->clubData, $this->cliente], ['situacao_pagamento', 'pagamento.status', 'payment_status'], '—');
    }

    public function nextDueDate(): string
    {
        return (string) DataExtractor::first([$this->clubData, $this->cliente], ['proximo_vencimento', 'data_vencimento', 'next_due_date', 'financeiro.proximo_vencimento'], '—');
    }

    private function firstContract(): array
    {
        $records = DataExtractor::get($this->cliente, 'contratos_portador.registros', []);
        if (is_array($records) && isset($records[0]) && is_array($records[0])) {
            return $records[0];
        }
        return [];
    }
}
