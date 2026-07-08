<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Services\AuthService;

if (!defined('ABSPATH')) {
    exit;
}

class AppShortcode
{
    public function register(): void
    {
        add_shortcode('serben_app', [$this, 'render']);
    }

    public function render(): string
    {
        wp_enqueue_style('serben-connect');

        if (!is_user_logged_in() || !(new AuthService())->getCurrentCpf()) {
            return '<div class="serben-app"><div class="serben-hero"><h2>Área do Associado</h2><p>Entre usando o CPF cadastrado no app Clube Serben.</p></div>' . (new LoginShortcode())->render() . '<hr>' . (new RegisterShortcode())->render() . '</div>';
        }

        $auth = new AuthService();
        $cliente = $auth->refreshCurrentUserFromApi();
        $cpf = $auth->getCurrentCpf();
        $user = wp_get_current_user();

        return '<div class="serben-app">'
            . '<div class="serben-hero serben-hero-row"><div><h2>Área do Associado</h2><p>Dados sincronizados com a API Clube Serben.</p></div>' . (new LoginShortcode())->renderLogout() . '</div>'
            . $this->dashboard($cliente ?: [], $cpf, $user)
            . '</div>';
    }

    private function dashboard(array $cliente, string $cpf, \WP_User $user): string
    {
        $nome = $cliente['nome_portador'] ?? $cliente['nome'] ?? $cliente['nome_cliente'] ?? $user->display_name;
        $email = $cliente['email_portador'] ?? $cliente['email'] ?? $user->user_email;
        $celular = $cliente['celular_portador'] ?? $cliente['celular'] ?? $cliente['fone'] ?? $cliente['telefone'] ?? '';
        $cartao = $cliente['numero_cartao'] ?? '';
        $loja = $cliente['nome_loja'] ?? '';
        $lastSync = get_user_meta($user->ID, 'serben_last_sync', true);

        $html = '<div class="serben-member-card">';
        $html .= '<span class="serben-kicker">Associado logado</span><h2>Olá, ' . esc_html((string)$nome) . '</h2>';
        $html .= '<div class="serben-data-grid">';
        $html .= $this->dataItem('CPF', $this->formatCpf($cpf));
        $html .= $this->dataItem('Usuário WP', $user->user_login);
        $html .= $this->dataItem('E-mail', $email);
        $html .= $this->dataItem('Celular', $celular);
        if ($cartao !== '') {
            $html .= $this->dataItem('Carteirinha', $cartao);
        }
        if ($loja !== '') {
            $html .= $this->dataItem('Loja', $loja);
        }
        $html .= $this->dataItem('Última sincronização', $lastSync ?: '—');
        $html .= '</div>';

        $html .= '<div class="serben-actions">'
            . '<button type="button" disabled>Minha Carteirinha</button>'
            . '<button type="button" disabled>Meu Plano</button>'
            . '<button type="button" disabled>Benefícios</button>'
            . '<button type="button" disabled>Financeiro</button>'
            . '<button type="button" disabled>Dependentes</button>'
            . '</div>';
        $html .= '<p class="serben-muted">Login por CPF ativo. Os módulos dos botões serão integrados nas próximas versões.</p>';
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

    private function formatCpf(string $cpf): string
    {
        $cpf = preg_replace('/\D+/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
}
