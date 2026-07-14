<?php
namespace SerbenConnect\Shortcodes;
if (!defined('ABSPATH')) { exit; }
class AccountShortcodes
{
    public function register():void
    {
        add_shortcode('serben_logged_in',[$this,'loggedIn']);
        add_shortcode('serben_logged_out',[$this,'loggedOut']);
        add_shortcode('serben_account_menu',[$this,'menu']);
        add_shortcode('serben_refresh_data',[$this,'refresh']);
    }
    public function loggedIn($atts=[],$content=''):string { return is_user_logged_in()?do_shortcode((string)$content):''; }
    public function loggedOut($atts=[],$content=''):string { return !is_user_logged_in()?do_shortcode((string)$content):''; }
    public function menu($atts=[]):string
    {
        if(!is_user_logged_in()) return '';
        $a=shortcode_atts(['home_url'=>'/minha-conta/','profile_url'=>'/minha-conta/perfil/','card_url'=>'/minha-conta/carteirinha/','plan_url'=>'/minha-conta/plano/','partners_url'=>'/parceiros/','financial_url'=>'/minha-conta/financeiro/'],$atts,'serben_account_menu');
        $items=['Início'=>$a['home_url'],'Perfil'=>$a['profile_url'],'Carteirinha'=>$a['card_url'],'Plano'=>$a['plan_url'],'Parceiros'=>$a['partners_url'],'Financeiro'=>$a['financial_url'],'Sair'=>wp_logout_url(home_url('/'))];
        $html='<nav class="serben-account-menu" aria-label="Menu do associado">'; foreach($items as $label=>$url){$html.='<a href="'.esc_url($url).'">'.esc_html($label).'</a>'; } return $html.'</nav>';
    }
    public function refresh($atts=[]):string
    {
        if(!is_user_logged_in()) return '';
        $a=shortcode_atts(['text'=>'Atualizar dados'],$atts,'serben_refresh_data');
        $url=wp_nonce_url(admin_url('admin-post.php?action=serben_refresh_member'),'serben_refresh_member');
        return '<a class="serben-refresh-button" href="'.esc_url($url).'">'.esc_html($a['text']).'</a>';
    }
}
