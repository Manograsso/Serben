<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Services\AuthService;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class LoginShortcode
{
    public function register(): void
    {
        add_shortcode('serben_login', [$this, 'render']);
        add_shortcode('serben_logout', [$this, 'renderLogout']);
    }

    public function render(): string
    {
        wp_enqueue_style('serben-connect');

        if (is_user_logged_in()) {
            return '<div class="serben-alert serben-success">Você já está logado.</div>' . $this->renderLogout();
        }

        $html = '<div class="serben-box serben-login"><h3>Entrar na Área do Associado</h3>';
        $html .= '<p class="serben-muted">Use o CPF cadastrado no app Clube Serben. No primeiro acesso ao site, crie uma senha para o seu usuário WordPress.</p>';

        if ($this->isLoginSubmitted()) {
            $cpf = preg_replace('/\D+/', '', (string) wp_unslash($_POST['serben_login_cpf'] ?? ''));
            $password = (string) wp_unslash($_POST['serben_login_password'] ?? '');
            $result = (new AuthService())->loginWithPassword($cpf, $password);
            $html .= $this->renderResult($result, 'Login realizado.');
            if (!empty($result['ok'])) {
                return $html . $this->refreshScript() . '</div>';
            }
        }

        if ($this->isFirstAccessSubmitted()) {
            $cpf = preg_replace('/\D+/', '', (string) wp_unslash($_POST['serben_first_cpf'] ?? ''));
            $password = (string) wp_unslash($_POST['serben_first_password'] ?? '');
            $confirm = (string) wp_unslash($_POST['serben_first_password_confirm'] ?? '');
            $result = (new AuthService())->createFirstAccess($cpf, $password, $confirm);
            $html .= $this->renderResult($result, 'Acesso criado com sucesso.');
            if (!empty($result['ok'])) {
                return $html . $this->refreshScript() . '</div>';
            }
        }

        $html .= '<div class="serben-login-grid">';
        $html .= $this->loginForm();
        $html .= $this->firstAccessForm();
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function loginForm(): string
    {
        $html = '<div class="serben-panel"><h4>Já tenho acesso</h4>';
        $html .= '<form method="post" class="serben-form">';
        $html .= wp_nonce_field('serben_login', 'serben_login_nonce', true, false);
        $html .= '<label>CPF<input type="text" name="serben_login_cpf" placeholder="Digite apenas números" inputmode="numeric" autocomplete="username" required></label>';
        $html .= '<label>Senha<input type="password" name="serben_login_password" autocomplete="current-password" required></label>';
        $html .= '<button type="submit">Entrar</button>';
        $html .= '</form></div>';
        return $html;
    }

    private function firstAccessForm(): string
    {
        $prefill = isset($_GET['cpf']) ? preg_replace('/\D+/', '', (string) wp_unslash($_GET['cpf'])) : '';
        $html = '<div class="serben-panel"><h4>Primeiro acesso no site</h4>';
        $html .= '<p class="serben-muted">Informe o CPF já cadastrado no app e crie uma senha para acessar o site.</p>';
        $html .= '<form method="post" class="serben-form">';
        $html .= wp_nonce_field('serben_first_access', 'serben_first_access_nonce', true, false);
        $html .= '<label>CPF<input type="text" name="serben_first_cpf" value="' . esc_attr($prefill) . '" placeholder="Digite apenas números" inputmode="numeric" autocomplete="username" required></label>';
        $html .= '<label>Criar senha<input type="password" name="serben_first_password" autocomplete="new-password" minlength="6" required></label>';
        $html .= '<label>Confirmar senha<input type="password" name="serben_first_password_confirm" autocomplete="new-password" minlength="6" required></label>';
        $html .= '<button type="submit">Criar meu acesso</button>';
        $html .= '</form></div>';
        return $html;
    }

    private function renderResult(array $result, string $successMessage): string
    {
        if (!empty($result['ok'])) {
            return '<div class="serben-alert serben-success"><strong>' . esc_html($successMessage) . '</strong><br>Atualizando sua área do associado...</div>';
        }

        $message = esc_html($result['message'] ?? 'Não foi possível concluir a operação.');
        $html = '<div class="serben-alert serben-error"><strong>Atenção.</strong><br>' . $message;

        if (!empty($result['not_found'])) {
            $url = $result['register_url'] ?? '';
            if ($url) {
                $html .= '<p><a class="serben-button" href="' . esc_url($url) . '">Ir para cadastro</a></p>';
                $html .= '<script>window.setTimeout(function(){ window.location.href = ' . wp_json_encode($url) . '; }, 900);</script>';
            } else {
                $html .= '<p class="serben-muted">Configure a URL da página de cadastro em Serben Connect → Configurações. Essa página deve conter o shortcode <code>[serben_register]</code>.</p>';
            }
        }

        $html .= '</div>';

        if (Settings::get('show_technical_front') === '1') {
            $html .= '<details class="serben-debug"><summary>Detalhes técnicos</summary><pre>' . esc_html(print_r($result['api_result'] ?? [], true)) . '</pre></details>';
        }

        return $html;
    }

    public function renderLogout(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['serben_logout_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_logout_nonce'])), 'serben_logout')) {
            wp_logout();
            return '<div class="serben-alert serben-success">Você saiu da Área do Associado.</div><script>window.setTimeout(function(){ window.location.href = window.location.href.split("#")[0]; }, 500);</script>';
        }

        $html = '<form method="post" class="serben-logout-form">';
        $html .= wp_nonce_field('serben_logout', 'serben_logout_nonce', true, false);
        $html .= '<button type="submit" class="serben-link-button">Sair</button>';
        $html .= '</form>';
        return $html;
    }

    private function isLoginSubmitted(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['serben_login_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_login_nonce'])), 'serben_login');
    }

    private function isFirstAccessSubmitted(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['serben_first_access_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_first_access_nonce'])), 'serben_first_access');
    }

    private function refreshScript(): string
    {
        return '<script>window.setTimeout(function(){ window.location.href = window.location.href.split("#")[0]; }, 600);</script><p class="serben-muted">Atualizando a área do associado...</p>';
    }
}
