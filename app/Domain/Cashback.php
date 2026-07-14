<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Cashback
{
    private $cliente;
    private $clubData;

    public function __construct(array $cliente, array $clubData = [])
    {
        $this->cliente = $cliente;
        $this->clubData = $clubData;
    }

    public function valueInCents()
    {
        return DataExtractor::first([$this->clubData, $this->cliente], [
            'saldos_portador.debito', 'saldo_debito_cartao', 'saldo_cashback',
            'cashback', 'saldoCashback', 'valor_cashback', 'saldo_disponivel',
            'fidelidade.cashback', 'saldo.cashback'
        ], null);
    }

    public function value(): ?float
    {
        $value = $this->valueInCents();
        return is_numeric($value) ? ((float) $value / 100) : null;
    }

    public function formatted(): string
    {
        $value = $this->value();
        return $value === null ? '—' : 'R$ ' . number_format($value, 2, ',', '.');
    }
}
