<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) { exit; }

class Partner
{
    private $postId;
    private $data;

    public function __construct(int $postId, array $data)
    {
        $this->postId = $postId;
        $this->data = $data;
    }

    public function id(): int { return $this->postId; }
    public function toArray(): array { return $this->data; }
    public function get(string $key, $default = null) { return $this->data[$key] ?? $default; }
    public function name(): string { return (string) $this->get('nome_fantasia', ''); }
    public function shortDescription(): string { return (string) $this->get('descricao_curta', ''); }
    public function fullDescription(): string { return (string) $this->get('descricao_completa', ''); }
    public function logoUrl(): string { return (string) $this->get('logo', ''); }
    public function coverUrl(): string { return (string) $this->get('foto_capa', ''); }
    public function gallery(): array { return (array) $this->get('galeria', []); }
    public function categories(): array { return (array) $this->get('partner_categories', $this->get('categoria-parceiro', [])); }
    public function localities(): array { return (array) $this->get('partner_localities', $this->get('localidade', [])); }
    public function benefitTypes(): array { return (array) $this->get('partner_benefit_types', $this->get('tipo-de-beneficio', [])); }
    public function isFeatured(): bool { return (bool) $this->get('parceiro_destaque', false); }
    public function hasCashback(): bool { return (bool) $this->get('tem_cashback', false); }
    public function hasDiscount(): bool { return (bool) $this->get('tem_desconto', false); }
}
