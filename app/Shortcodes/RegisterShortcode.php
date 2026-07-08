<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class RegisterShortcode
{
    public function register(): void
    {
        add_shortcode('serben_register', [$this, 'render']);
    }

    public function render(): string
    {
        wp_enqueue_style('serben-connect');
        $prefillCpf = isset($_GET['cpf']) ? preg_replace('/\D+/', '', (string) wp_unslash($_GET['cpf'])) : '';
        $html = '<div class="serben-box"><h3>Cadastrar cliente</h3>';
        $html .= '<p class="serben-muted">Preencha os dados para criar seu cadastro no Clube Serben. Depois de cadastrado, volte para a Área do Associado e faça o primeiro acesso.</p>';

        if ($this->isSubmitted()) {
            $result = (new ClientesService())->cadastrar(wp_unslash($_POST['serben'] ?? []));
            if (!empty($result['ok'])) {
                $html .= '<div class="serben-alert serben-success"><strong>Cadastro enviado com sucesso.</strong></div>';
            } else {
                $html .= '<div class="serben-alert serben-error"><strong>Falha ao cadastrar.</strong><br>Verifique os dados informados ou consulte os logs técnicos no painel.</div>';
            }

            if (Settings::get('show_technical_front') === '1') {
                $html .= '<details class="serben-debug"><summary>Resposta da API</summary><pre>' . esc_html(print_r($result['body'] ?? $result['raw'] ?? '', true)) . '</pre></details>';
            }
        }

        $html .= '<form method="post" class="serben-form serben-grid">';
        $html .= wp_nonce_field('serben_register', 'serben_register_nonce', true, false);
        $fields = [
            'cpf_cnpj' => 'CPF',
            'nome' => 'Nome',
            'rg' => 'RG',
            'sexo' => 'Sexo (F/M)',
            'data_nasc' => 'Data nasc. (AAAA-MM-DD)',
            'fone' => 'Telefone',
            'celular' => 'Celular',
            'email' => 'E-mail',
            'estado' => 'UF',
            'cidade' => 'Cidade',
            'bairro' => 'Bairro',
            'cep' => 'CEP',
            'endereco' => 'Endereço',
            'numero' => 'Número',
            'complemento' => 'Complemento',
            'cnpj_corporacao' => 'CNPJ Corporação (opcional)',
        ];

        foreach ($fields as $name => $label) {
            $type = $name === 'email' ? 'email' : 'text';
            $required = in_array($name, ['cpf_cnpj','nome','sexo','data_nasc','fone','celular','email','estado','cidade','bairro','cep','endereco','numero'], true) ? ' required' : '';
            $value = ($name === 'cpf_cnpj' && $prefillCpf) ? $prefillCpf : '';
            $html .= '<label>' . esc_html($label) . '<input type="' . esc_attr($type) . '" name="serben[' . esc_attr($name) . ']" value="' . esc_attr($value) . '"' . $required . '></label>';
        }

        $html .= '<button type="submit">Cadastrar</button>';
        $html .= '</form></div>';
        return $html;
    }

    private function isSubmitted(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['serben_register_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_register_nonce'])), 'serben_register');
    }
}
