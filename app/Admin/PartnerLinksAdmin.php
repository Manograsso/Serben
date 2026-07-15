<?php
namespace SerbenConnect\Admin;

use SerbenConnect\Partners\Auth\PartnerLinker;

if (!defined('ABSPATH')) { exit; }

final class PartnerLinksAdmin
{
    public function register(): void
    {
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_post_serben_relink_partner',[$this,'relink']);
        add_action('admin_post_serben_unlink_partner',[$this,'unlink']);
    }
    public function menu(): void { add_submenu_page('serben-connect','Vínculos','Vínculos','manage_options','serben-connect-links',[$this,'page']); }
    public function page(): void
    {
        if(!current_user_can('manage_options')){return;}
        $users=get_users(['role'=>'serben_lojista','orderby'=>'display_name','order'=>'ASC']);
        echo '<div class="wrap"><h1>Vínculos de Lojistas</h1>';
        if(isset($_GET['updated'])){echo '<div class="notice notice-success"><p>Vínculo atualizado.</p></div>';}
        echo '<table class="widefat striped"><thead><tr><th>Usuário</th><th>CNPJ</th><th>Loja API</th><th>Parceiro WP</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
        foreach($users as $user){
            $cnpj=(string)get_user_meta($user->ID,'serben_partner_cnpj',true);
            $store=(string)get_user_meta($user->ID,'serben_store_id',true);
            $postId=(int)get_user_meta($user->ID,'serben_partner_post_id',true);
            $status=$postId&&get_post($postId)?'Vinculado':'Sem vínculo';
            $relink=wp_nonce_url(admin_url('admin-post.php?action=serben_relink_partner&user_id='.$user->ID),'serben_relink_partner_'.$user->ID);
            $unlink=wp_nonce_url(admin_url('admin-post.php?action=serben_unlink_partner&user_id='.$user->ID),'serben_unlink_partner_'.$user->ID);
            echo '<tr><td><a href="'.esc_url(get_edit_user_link($user->ID)).'">'.esc_html($user->display_name).'</a></td><td>'.esc_html($cnpj).'</td><td>'.esc_html($store?:'—').'</td><td>'.($postId?'<a href="'.esc_url(get_edit_post_link($postId)).'">#'.(int)$postId.' '.esc_html(get_the_title($postId)).'</a>':'—').'</td><td>'.esc_html($status).'</td><td><a class="button" href="'.esc_url($relink).'">Refazer vínculo</a> <a class="button" href="'.esc_url($unlink).'">Desvincular</a></td></tr>';
        }
        if(!$users){echo '<tr><td colspan="6">Nenhum lojista cadastrado.</td></tr>';}
        echo '</tbody></table></div>';
    }
    public function relink(): void
    {
        $userId=absint($_GET['user_id']??0);
        if(!current_user_can('manage_options')||!wp_verify_nonce($_GET['_wpnonce']??'','serben_relink_partner_'.$userId)){wp_die('Acesso negado.');}
        $cnpj=(string)get_user_meta($userId,'serben_partner_cnpj',true);
        $raw=(string)get_user_meta($userId,'serben_partner_store_data',true);
        $store=json_decode($raw,true); if(!is_array($store)){$store=[];}
        (new PartnerLinker())->link($userId,$cnpj,$store);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-links&updated=1'));exit;
    }
    public function unlink(): void
    {
        $userId=absint($_GET['user_id']??0);
        if(!current_user_can('manage_options')||!wp_verify_nonce($_GET['_wpnonce']??'','serben_unlink_partner_'.$userId)){wp_die('Acesso negado.');}
        $postId=(int)get_user_meta($userId,'serben_partner_post_id',true);
        if($postId){delete_post_meta($postId,'_serben_wp_user_id');delete_post_meta($postId,'_serben_store_id');}
        delete_user_meta($userId,'serben_partner_post_id');
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-links&updated=1'));exit;
    }
}
