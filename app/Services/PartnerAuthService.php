<?php
namespace SerbenConnect\Services;

use SerbenConnect\Partners\Auth\PartnerLinker;

if (!defined('ABSPATH')) { exit; }

final class PartnerAuthService
{
    private $lojas;
    public function __construct(?LojasService $lojas = null) { $this->lojas = $lojas ?: new LojasService(); }

    public function login(string $cnpj, string $password): array
    {
        $cnpj = $this->normalize($cnpj);
        $user = $this->findUser($cnpj);
        if (!$user) {
            $store = $this->lojas->buscarPorCnpj($cnpj);
            return $store ? ['ok'=>false,'first_access'=>true,'message'=>'Loja localizada. Use Primeiro acesso para criar a senha.'] : ['ok'=>false,'message'=>'Loja não localizada na API Serben.'];
        }
        if (!wp_check_password($password, $user->user_pass, $user->ID)) { return ['ok'=>false,'message'=>'Senha incorreta.']; }
        $this->authenticate((int)$user->ID);
        return ['ok'=>true,'user_id'=>(int)$user->ID,'message'=>'Login realizado.'];
    }

    public function firstAccess(string $cnpj, string $password, string $confirm): array
    {
        $cnpj = $this->normalize($cnpj);
        if (strlen($cnpj)!==14) { return ['ok'=>false,'message'=>'Informe um CNPJ válido.']; }
        if (strlen($password)<6 || $password!==$confirm) { return ['ok'=>false,'message'=>'A senha deve ter ao menos 6 caracteres e a confirmação deve coincidir.']; }
        if ($this->findUser($cnpj)) { return ['ok'=>false,'message'=>'Este parceiro já possui acesso.']; }
        $store = $this->lojas->buscarPorCnpj($cnpj);
        if (!$store) { return ['ok'=>false,'message'=>'CNPJ não localizado entre as lojas do credenciador.']; }
        $name = sanitize_text_field((string)($store['nome_fantasia'] ?? $store['nome'] ?? $store['razao_social'] ?? 'Lojista Serben'));
        $email = sanitize_email((string)($store['email'] ?? $store['email_principal'] ?? $store['email_loja'] ?? ''));
        if (!$email) { $email = 'lojista_' . $cnpj . '@serben.local'; }
        $userId = wp_insert_user([
            'user_login' => 'serben_lojista_' . $cnpj,
            'user_pass' => $password,
            'user_email' => $email,
            'display_name' => $name,
            'role' => 'serben_lojista',
        ]);
        if (is_wp_error($userId)) { return ['ok'=>false,'message'=>$userId->get_error_message()]; }
        update_user_meta($userId, 'serben_identity_type', 'partner');
        update_user_meta($userId, 'serben_documento', $cnpj);
        update_user_meta($userId, 'serben_partner_store_data', wp_json_encode($store));
        $link = (new PartnerLinker())->link((int)$userId, $cnpj, $store);
        $this->authenticate((int)$userId);
        return ['ok'=>true,'user_id'=>(int)$userId,'link'=>$link,'message'=>$link['ok'] ? 'Acesso criado e parceiro vinculado.' : 'Acesso criado, mas o post do parceiro ainda não foi vinculado.'];
    }

    private function findUser(string $cnpj): ?\WP_User
    {
        $users = get_users(['meta_key'=>'serben_partner_cnpj','meta_value'=>$cnpj,'number'=>1,'fields'=>'all']);
        if ($users && $users[0] instanceof \WP_User) { return $users[0]; }
        $user = get_user_by('login', 'serben_lojista_' . $cnpj);
        return $user instanceof \WP_User ? $user : null;
    }
    private function normalize(string $value): string { return preg_replace('/\D+/', '', $value); }
    private function authenticate(int $userId): void
    {
        wp_clear_auth_cookie(); wp_set_current_user($userId); wp_set_auth_cookie($userId, true);
        $user=get_userdata($userId); if($user){do_action('wp_login',$user->user_login,$user);} 
    }
}
