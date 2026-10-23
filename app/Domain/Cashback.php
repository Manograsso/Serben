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
        // Apenas campos comprovadamente relacionados ao cashback. Não usar crédito,
        // CCI ou valores genéricos como fallback de saldo financeiro.
        return DataExtractor::first([$this->clubData, $this->cliente], [
            'saldo_cashback_liberado', 'saldo_cashback_disponivel',
            'cartoes.0.saldo_cashback_liberado', 'cartoes.0.saldo_cashback_disponivel',
            'saldo_cashback', 'cashback', 'saldoCashback', 'fidelidade.cashback'
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
