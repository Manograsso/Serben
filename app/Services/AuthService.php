<?php
namespace SerbenConnect\Services;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class AuthService
{
    private $clientes;

    public function __construct(?ClientesService $clientes = null)
    {
        $this->clientes = $clientes ?: new ClientesService();
    }

    public function loginWithPassword(string $cpf, string $password): array
    {
        $cpf = $this->normalizeCpf($cpf);

        if (!in_array(strlen($cpf), [11,14], true) || $password === '') {
            return ['ok' => false, 'message' => 'Informe CPF/CNPJ e senha.', 'user_id' => 0];
        }

        $user = $this->findUserByCpf($cpf);
        if (!$user) {
            $exists = $this->cpfExistsInApi($cpf);
            if ($exists['exists']) {
                return ['ok' => false, 'message' => 'Este CPF/CNPJ existe no app, mas ainda não possui acesso no site. Use a opção Primeiro acesso para criar sua senha.', 'first_access' => true, 'api_result' => $exists['api_result'] ?? null];
            }
            return $this->notFoundResult($cpf, $exists['api_result'] ?? null);
        }

        if (!wp_check_password($password, $user->user_pass, $user->ID)) {
            return ['ok' => false, 'message' => 'Senha incorreta.', 'user_id' => $user->ID];
        }

        $this->authenticate((int) $user->ID);
        $this->refreshUserByCpf((int) $user->ID, $cpf);

        return ['ok' => true, 'message' => 'Login realizado com sucesso.', 'user_id' => (int) $user->ID];
    }

    public function createFirstAccess(string $cpf, string $password, string $passwordConfirm): array
    {
        $cpf = $this->normalizeCpf($cpf);

        if (!in_array(strlen($cpf), [11,14], true)) {
            return ['ok' => false, 'message' => 'Informe um CPF ou CNPJ válido.', 'user_id' => 0];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'A senha precisa ter pelo menos 6 caracteres.', 'user_id' => 0];
        }
        if ($password !== $passwordConfirm) {
            return ['ok' => false, 'message' => 'A confirmação de senha não confere.', 'user_id' => 0];
        }

        $existing = $this->findUserByCpf($cpf);
        if ($existing) {
            return ['ok' => false, 'message' => 'Este CPF/CNPJ já possui acesso no site. Use a opção Já tenho acesso.', 'user_id' => $existing->ID];
        }

        $result = $this->clientes->buscarPorDocumento($cpf);
        $cliente = $this->clientes->extractCliente($result);

        if (!$cliente) {
            return $this->notFoundResult($cpf, $result);
        }

        $userId = $this->createUserFromCliente($cpf, $cliente, $password);
        if (is_wp_error($userId)) {
            return ['ok' => false, 'message' => $userId->get_error_message(), 'api_result' => $result, 'user_id' => 0];
        }

        $this->syncUserMeta((int) $userId, $cpf, $cliente, $result);
        $this->authenticate((int) $userId);

        return ['ok' => true, 'message' => 'Acesso criado com sucesso.', 'api_result' => $result, 'user_id' => (int) $userId, 'cliente' => $cliente];
    }

    /** Mantido para compatibilidade com versões anteriores. Não deve ser usado em produção. */
    public function loginByCpf(string $cpf): array
    {
        $cpf = $this->normalizeCpf($cpf);
        $exists = $this->cpfExistsInApi($cpf);
        if (!$exists['exists']) {
            return $this->notFoundResult($cpf, $exists['api_result'] ?? null);
        }
        $user = $this->findUserByCpf($cpf);
        if (!$user) {
            return ['ok' => false, 'message' => 'Documento localizado. Crie uma senha no Primeiro acesso.', 'first_access' => true, 'api_result' => $exists['api_result'] ?? null, 'user_id' => 0];
        }
        $this->authenticate((int) $user->ID);
        return ['ok' => true, 'message' => 'Login realizado.', 'user_id' => (int) $user->ID];
    }

    public function getCurrentCliente(): ?array
    {
        if (!is_user_logged_in()) {
            return null;
        }

        $userId = get_current_user_id();
        $json = get_user_meta($userId, 'serben_cliente_data', true);
        $cliente = is_string($json) ? json_decode($json, true) : null;

        return is_array($cliente) ? $cliente : null;
    }

    public function getCurrentCpf(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }
        $userId = get_current_user_id();
        $document = (string) get_user_meta($userId, 'serben_documento', true);
        if ($document !== '') {
            return $document;
        }
        $cpf = (string) get_user_meta($userId, 'serben_cpf', true);
        if ($cpf !== '') {
            return $cpf;
        }
        return (string) get_user_meta($userId, 'serben_cnpj', true);
    }

    public function refreshCurrentUserFromApi(): ?array
    {
        $cpf = $this->getCurrentCpf();
        if (!$cpf) {
            return $this->getCurrentCliente();
        }

        return $this->refreshUserByCpf(get_current_user_id(), $cpf) ?: $this->getCurrentCliente();
    }

    private function cpfExistsInApi(string $cpf): array
    {
        if (!in_array(strlen($cpf), [11,14], true)) {
            return ['exists' => false, 'api_result' => null, 'cliente' => null];
        }
        $result = $this->clientes->buscarPorDocumento($cpf);
        $cliente = $this->clientes->extractCliente($result);
        return ['exists' => (bool) $cliente, 'api_result' => $result, 'cliente' => $cliente];
    }

    private function notFoundResult(string $cpf, $apiResult = null): array
    {
        $url = Settings::get('register_page_url');
        if ($url) {
            $url = add_query_arg('cpf', rawurlencode($cpf), $url);
        }
        return [
            'ok' => false,
            'message' => 'CPF/CNPJ não localizado no app Clube Serben. Faça seu cadastro para continuar.',
            'not_found' => true,
            'register_url' => $url,
            'api_result' => $apiResult,
            'user_id' => 0,
        ];
    }

    private function findUserByCpf(string $cpf): ?\WP_User
    {
        foreach (['serben_documento', 'serben_cpf', 'serben_cnpj'] as $metaKey) {
            $existing = get_users([
                'meta_key' => $metaKey,
                'meta_value' => $cpf,
                'number' => 1,
                'fields' => 'all',
            ]);

            if (!empty($existing) && $existing[0] instanceof \WP_User) {
                if ($metaKey !== 'serben_documento') {
                    update_user_meta((int) $existing[0]->ID, 'serben_documento', $cpf);
                }
                return $existing[0];
            }
        }

        $username = 'serben_' . $cpf;
        $user = get_user_by('login', $username);
        return $user instanceof \WP_User ? $user : null;
    }

    private function createUserFromCliente(string $cpf, array $cliente, string $password)
    {
        $username = 'serben_' . $cpf;
        if (username_exists($username)) {
            return new \WP_Error('serben_user_exists', 'Já existe um usuário WordPress para este CPF/CNPJ.');
        }

        $email = $this->extractEmail($cliente);
        if ($email && email_exists($email)) {
            $userId = (int) email_exists($email);
            update_user_meta($userId, 'serben_cpf', strlen($cpf) === 11 ? $cpf : '');
            update_user_meta($userId, 'serben_cnpj', strlen($cpf) === 14 ? $cpf : '');
            update_user_meta($userId, 'serben_documento', $cpf);
            update_user_meta($userId, 'serben_identity_type', strlen($cpf) === 14 ? 'company_customer' : 'member');
            wp_set_password($password, $userId);
            return $userId;
        }

        if (!$email) {
            $email = $cpf . '@serben.local';
        }

        $name = $this->extractName($cliente);

        return wp_insert_user([
            'user_login' => $username,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name ?: $cpf,
            'first_name' => $name,
            'role' => strlen($cpf) === 14 ? 'serben_empresa_cliente' : 'serben_associado',
        ]);
    }

    private function refreshUserByCpf(int $userId, string $cpf): ?array
    {
        $result = $this->clientes->buscarPorDocumento($cpf);
        $cliente = $this->clientes->extractCliente($result);

        if ($cliente) {
            $this->syncUserMeta($userId, $cpf, $cliente, $result);
            return $cliente;
        }

        return null;
    }

    private function syncUserMeta(int $userId, string $cpf, array $cliente, array $apiResult): void
    {
        $name = $this->extractName($cliente);
        $email = $this->extractEmail($cliente);

        $update = [
            'ID' => $userId,
        ];
        if ($name) {
            $update['display_name'] = $name;
            $update['first_name'] = $name;
        }
        if ($email && !email_exists($email)) {
            $update['user_email'] = $email;
        }
        if (count($update) > 1) {
            wp_update_user($update);
        }

        update_user_meta($userId, 'serben_cpf', strlen($cpf) === 11 ? $cpf : '');
        update_user_meta($userId, 'serben_cnpj', strlen($cpf) === 14 ? $cpf : '');
        update_user_meta($userId, 'serben_documento', $cpf);
        update_user_meta($userId, 'serben_identity_type', strlen($cpf) === 14 ? 'company_customer' : 'member');
        update_user_meta($userId, 'serben_cliente_data', wp_json_encode($cliente));
        update_user_meta($userId, 'serben_cliente_api_last_code', (string)($apiResult['code'] ?? ''));
        update_user_meta($userId, 'serben_cliente_api_last_url', (string)($apiResult['url'] ?? ''));
        update_user_meta($userId, 'serben_last_sync', current_time('mysql'));
    }

    private function authenticate(int $userId): void
    {
        wp_clear_auth_cookie();
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, true);
        $user = get_userdata($userId);
        if ($user) {
            do_action('wp_login', $user->user_login, $user);
        }
    }

    private function normalizeCpf(string $cpf): string
    {
        return preg_replace('/\D+/', '', $cpf);
    }

    private function extractName(array $cliente): string
    {
        return sanitize_text_field((string)($cliente['nome_portador'] ?? $cliente['nome'] ?? $cliente['nome_cliente'] ?? $cliente['razao_social'] ?? 'Associado Serben'));
    }

    private function extractEmail(array $cliente): string
    {
        $email = (string)($cliente['email_portador'] ?? $cliente['email'] ?? $cliente['email_cliente'] ?? '');
        return is_email($email) ? sanitize_email($email) : '';
    }
}
