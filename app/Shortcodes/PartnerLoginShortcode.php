<?php
namespace SerbenConnect\Shortcodes;
if(!defined('ABSPATH'))exit;
final class PartnerLoginShortcode {
 public function register():void {add_shortcode('serben_partner_login',[$this,'render']);add_shortcode('serben_partner_account',[$this,'render']);}
 public function render():string {
  if(is_user_logged_in())return '<div class="serben-alert serben-success">Você já está logado.</div>';
  $msg='';
  if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&isset($_POST['serben_secure_login'])){
   $nonce=sanitize_text_field(wp_unslash($_POST['serben_login_nonce']??''));
   if(!wp_verify_nonce($nonce,'serben_secure_login')){$msg='Sessão inválida. Atualize a página.';}
   else {
    $email=sanitize_email(wp_unslash($_POST['serben_login_email']??''));$password=(string)wp_unslash($_POST['serben_login_password']??'');
    $user=get_user_by('email',$email);
    if(!$user||!\SerbenConnect\Admin\AccessManagement::accountAllowed((int)$user->ID)||!array_intersect((array)$user->roles,['serben_lojista','serben_entidade','serben_entidade_diretoria','serben_entidade_secretaria','serben_entidade_atendente'])){$msg='Credenciais inválidas ou acesso não aprovado.';}
    else {
     $auth=wp_signon(['user_login'=>$user->user_login,'user_password'=>$password,'remember'=>false],is_ssl());
     if(is_wp_error($auth))$msg='Credenciais inválidas ou acesso não aprovado.';
     else return '<div class="serben-alert serben-success">Login realizado. <a href="'.esc_url(home_url('/')).'">Continuar</a></div><script>location.reload();</script>';
    }
   }
  }
  $out='<div class="serben-box"><h3>Acesso de parceiros e entidades</h3>';
  if($msg)$out.='<p class="serben-alert serben-error">'.esc_html($msg).'</p>';
  $out.='<form method="post">'.wp_nonce_field('serben_secure_login','serben_login_nonce',true,false).'<input type="hidden" name="serben_secure_login" value="1"><p><label>E-mail <input type="email" name="serben_login_email" required autocomplete="username"></label></p><p><label>Senha <input type="password" name="serben_login_password" required autocomplete="current-password"></label></p><button type="submit">Entrar</button></form>';
  $out.='<p><a href="'.esc_url(wp_lostpassword_url()).'">Esqueci minha senha</a></p><p>Primeiro acesso? Solicite aprovação ao administrador.</p></div>';return $out;
 }
}
