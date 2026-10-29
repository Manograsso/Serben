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
        if (!\SerbenConnect\Admin\AccessManagement::accountAllowed((int)$user->ID)) { return ['ok'=>false,'message'=>'Acesso revogado ou não aprovado.']; }
        if (!wp_check_password($password, $user->user_pass, $user->ID)) { return ['ok'=>false,'message'=>'Senha incorreta.']; }
        $this->authenticate((int)$user->ID);
        return ['ok'=>true,'user_id'=>(int)$user->ID,'message'=>'Login realizado.'];
    }

    public function firstAccess(string $cnpj, string $password, string $confirm): array
    {
        return ['ok'=>false,'first_access'=>false,'message'=>'O primeiro acesso por CNPJ foi desativado. Solicite autorização pela Gestão de Acessos.'];
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
