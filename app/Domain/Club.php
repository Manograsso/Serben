<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Club
{
    private $data;
    private $meta;

    public function __construct(array $data = [], array $meta = [])
    {
        $this->data = $data;
        $this->meta = $meta;
    }

    public function isLinked(): bool { return !empty($this->meta['linked']); }
    public function statusReturn(): ?int { return isset($this->meta['status_retorno']) ? (int) $this->meta['status_retorno'] : null; }
    public function storeId(): string { return (string) DataExtractor::first([$this->data, $this->meta], ['idLoja', 'id_loja'], ''); }
    public function usesDebit(): bool { return (int) DataExtractor::get($this->data, 'configurador_loja.usa_debito', 0) === 1; }
    public function usesCredit(): bool { return (int) DataExtractor::get($this->data, 'configurador_loja.usa_credito', 0) === 1; }
    public function usesPoints(): bool { return (int) DataExtractor::get($this->data, 'configurador_loja.usa_pontos', 0) === 1; }
    public function fastRegistrationEnabled(): bool { return (int) DataExtractor::get($this->data, 'configurador_loja.habilita_cadastro_ultra_rapido', 0) === 1; }
    public function message(): string { return $this->isLinked() ? 'Vínculo ativo com o clube.' : 'Este associado não possui vínculo ativo com esta unidade.'; }
    public function raw(): array { return ['data' => $this->data, 'meta' => $this->meta]; }
}
