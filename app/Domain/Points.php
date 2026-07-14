<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Points
{
    private $cliente;
    private $clubData;

    public function __construct(array $cliente, array $clubData = [])
    {
        $this->cliente = $cliente;
        $this->clubData = $clubData;
    }

    public function value()
    {
        return DataExtractor::first([$this->clubData, $this->cliente], [
            'saldos_portador.pontos', 'saldo_pontos', 'pontos', 'saldoPontos',
            'saldo_ponto', 'pontuacao', 'saldo_fidelidade_pontos',
            'fidelidade.pontos', 'saldo.points'
        ], null);
    }

    public function formatted(): string
    {
        return DataExtractor::number($this->value());
    }
}
