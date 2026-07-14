<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Card
{
    private $cliente;
    private $clubData;

    public function __construct(array $cliente, array $clubData = [])
    {
        $this->cliente = $cliente;
        $this->clubData = $clubData;
    }

    public function number(): string
    {
        return (string) DataExtractor::first([$this->clubData, $this->cliente], ['numero_cartao', 'cartao.numero', 'cartao', 'card_number'], '—');
    }

    public function statusCode()
    {
        return DataExtractor::first([$this->clubData, $this->cliente], ['status_cartao', 'cartao.status', 'card_status'], null);
    }

    public function status(): string
    {
        $status = $this->statusCode();
        if ($status === 1 || $status === '1') { return 'Ativo'; }
        if ($status === 0 || $status === '0') { return 'Inativo'; }
        return (string) ($status ?? '—');
    }

    public function expirationRaw(): string
    {
        return preg_replace('/\D+/', '', (string) DataExtractor::first([$this->clubData, $this->cliente], ['validade_cartao', 'cartao.validade', 'card_expiration'], ''));
    }

    public function expiration(): string
    {
        $value = $this->expirationRaw();
        if (strlen($value) !== 4) { return $value ?: '—'; }
        return substr($value, 0, 2) . '/20' . substr($value, 2, 2);
    }

    public function photoUrl(): string
    {
        return (string) DataExtractor::first([$this->clubData, $this->cliente], ['foto_portador', 'foto', 'avatar'], '');
    }
}
