<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class LookupShortcode
{
    public function register(): void
    {
        add_shortcode('serben_lookup', [$this, 'render']);
    }

    public function render(): string
    {
        wp_enqueue_style('serben-connect');

        $html = '<div class="serben-box serben-lookup"><h3>Consultar associado</h3>';

        if ($this->isSubmitted()) {
            $cpf = preg_replace('/\D+/', '', (string) wp_unslash($_POST['serben_cpf'] ?? ''));
            $html .= $this->renderResult($cpf);
        }

        $html .= '<form method="post" class="serben-form">';
        $html .= wp_nonce_field('serben_lookup', 'serben_lookup_nonce', true, false);
        $html .= '<label>CPF do associado<input type="text" name="serben_cpf" placeholder="Digite apenas números" required></label>';
        $html .= '<button type="submit">Consultar CPF</button>';
        $html .= '</form></div>';

        return $html;
    }

    private function isSubmitted(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['serben_lookup_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_lookup_nonce'])), 'serben_lookup');
    }

    private function renderResult(string $cpf): string
    {
        if (!$cpf) {
            return '<div class="serben-alert serben-error">Informe um CPF válido.</div>';
        }

        $service = new ClientesService();
        $clienteResult = $service->buscarPorDocumento($cpf);
        $cliente = $service->extractCliente($clienteResult);

        // Tentativa silenciosa. Se retornar 403, fica apenas nos logs técnicos.
        $portadorResult = $service->buscarPortadorOpcional($cpf);
        $portador = $service->extractPortador($portadorResult);

        if (!$cliente && !$portador) {
            $message = '<div class="serben-alert serben-warning"><strong>CPF não localizado.</strong><br>Não encontramos cadastro para este CPF na API Serben.</div>';
            return $message . $this->maybeDebug($clienteResult, $portadorResult);
        }

        $source = $portador ?: $cliente;
        $html = '<div class="serben-alert serben-success"><strong>Cliente encontrado.</strong></div>';
        $html .= $this->memberCard($source, $cliente, $portador);
        $html .= $this->quickActions();
        $html .= $this->maybeDebug($clienteResult, $portadorResult);
        return $html;
    }

    private function memberCard(array $source, ?array $cliente, ?array $portador): string
    {
        $nome = $source['nome_portador'] ?? $source['nome'] ?? $source['nome_cliente'] ?? 'Associado';
        $cpf = $source['cpf_portador'] ?? $source['cpf_cnpj'] ?? $source['documento'] ?? $source['cpf'] ?? '';
        $email = $source['email_portador'] ?? $source['email'] ?? '';
        $celular = $source['celular_portador'] ?? $source['celular'] ?? $source['fone'] ?? $source['telefone'] ?? '';
        $cartao = $source['numero_cartao'] ?? '';
        $loja = $source['nome_loja'] ?? '';

        $html = '<div class="serben-member-card">';
        $html .= '<div><span class="serben-kicker">Área do Associado</span><h2>Olá, ' . esc_html($nome) . '</h2></div>';
        $html .= '<div class="serben-data-grid">';
        $html .= $this->dataItem('CPF', $cpf);
        $html .= $this->dataItem('E-mail', $email);
        $html .= $this->dataItem('Celular', $celular);
        if ($cartao !== '') {
            $html .= $this->dataItem('Carteirinha', $cartao);
        }
        if ($loja !== '') {
            $html .= $this->dataItem('Loja', $loja);
        }

        $contratos = $portador['contratos_portador']['registros'] ?? [];
        if (is_array($contratos) && !empty($contratos)) {
            $first = $contratos[0];
            $html .= $this->dataItem('Plano', $first['nome_plano'] ?? '');
            $html .= $this->dataItem('Contrato', $first['situacao_contrato'] ?? '');
            $html .= $this->dataItem('Pagamento', $first['situacao_pagamento'] ?? '');
        }

        $html .= '</div>';

        if (is_array($contratos) && !empty($contratos)) {
            $html .= '<h4>Contratos</h4><ul class="serben-contracts">';
            foreach ($contratos as $contrato) {
                $html .= '<li><strong>' . esc_html($contrato['nome_plano'] ?? 'Plano') . '</strong> — ' . esc_html($contrato['situacao_contrato'] ?? '') . ' — ' . esc_html($contrato['situacao_pagamento'] ?? '') . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</div>';
        return $html;
    }

    private function dataItem(string $label, $value): string
    {
        if ($value === null || $value === '') {
            $value = '—';
        }
        return '<div class="serben-data-item"><span>' . esc_html($label) . '</span><strong>' . esc_html((string)$value) . '</strong></div>';
    }

    private function quickActions(): string
    {
        return '<div class="serben-actions"><button type="button" disabled>Minha Carteirinha</button><button type="button" disabled>Benefícios</button><button type="button" disabled>Cashback</button><button type="button" disabled>Financeiro</button></div><p class="serben-muted">Os botões acima são a base visual da área do associado. As integrações serão adicionadas nos próximos módulos.</p>';
    }

    private function maybeDebug(array $clienteResult, array $portadorResult): string
    {
        if (Settings::get('show_technical_front') !== '1') {
            return '';
        }

        return '<details class="serben-debug"><summary>Detalhes técnicos da API</summary><pre>' . esc_html(print_r([
            'cliente' => $clienteResult,
            'portador_opcional' => $portadorResult,
        ], true)) . '</pre></details>';
    }
}
