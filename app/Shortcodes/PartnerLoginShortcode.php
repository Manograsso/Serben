<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Services\PartnerAuthService;

if (!defined('ABSPATH')) { exit; }

final class PartnerLoginShortcode
{
    public function register(): void
    {
        add_shortcode('serben_partner_login', [$this,'render']);
        add_shortcode('serben_partner_account', [$this,'render']);
    }
    public function render(): string
    {
        wp_enqueue_style('serben-connect');
        if (is_user_logged_in()) { return '<div class="serben-alert serben-success">Você já está logado.</div>'; }
        $html='<div class="serben-box"><h3>Acesso do Parceiro</h3>';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['serben_partner_action'])) {
            $nonce=sanitize_text_field(wp_unslash($_POST['serben_partner_nonce'] ?? ''));
            if (wp_verify_nonce($nonce,'serben_partner_auth')) {
                $service=new PartnerAuthService();
                $cnpj=preg_replace('/\D+/','',(string)wp_unslash($_POST['serben_partner_cnpj'] ?? ''));
                $pass=(string)wp_unslash($_POST['serben_partner_password'] ?? '');
                if ($_POST['serben_partner_action']==='first') {
                    $result=$service->firstAccess($cnpj,$pass,(string)wp_unslash($_POST['serben_partner_confirm'] ?? ''));
                } else { $result=$service->login($cnpj,$pass); }
                $class=!empty($result['ok'])?'serben-success':'serben-error';
                $html.='<div class="serben-alert '.$class.'">'.esc_html($result['message'] ?? '').'</div>';
                if(!empty($result['ok'])){ return $html.'<script>setTimeout(function(){location.reload();},700);</script></div>'; }
            }
        }
        $nonce=wp_nonce_field('serben_partner_auth','serben_partner_nonce',true,false);
        $html.='<div class="serben-login-grid"><div class="serben-panel"><h4>Já tenho acesso</h4><form method="post" class="serben-form">'.$nonce.'<input type="hidden" name="serben_partner_action" value="login"><label>CNPJ<input name="serben_partner_cnpj" inputmode="numeric" required></label><label>Senha<input type="password" name="serben_partner_password" required></label><button type="submit">Entrar</button></form></div>';
        $html.='<div class="serben-panel"><h4>Primeiro acesso</h4><form method="post" class="serben-form">'.$nonce.'<input type="hidden" name="serben_partner_action" value="first"><label>CNPJ<input name="serben_partner_cnpj" inputmode="numeric" required></label><label>Criar senha<input type="password" name="serben_partner_password" minlength="6" required></label><label>Confirmar senha<input type="password" name="serben_partner_confirm" minlength="6" required></label><button type="submit">Criar acesso</button></form></div></div></div>';
        return $html;
    }
}
