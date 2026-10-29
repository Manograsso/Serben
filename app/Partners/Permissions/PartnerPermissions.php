<?php
namespace SerbenConnect\Partners\Permissions;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class PartnerPermissions
{
    public function register(): void
    {
        add_filter('map_meta_cap', [$this,'mapMetaCap'],10,4);
        add_action('pre_get_posts', [$this,'restrictAdminList']);
        add_action('admin_init', [$this,'guardPostAccess']);
    }

    public function mapMetaCap(array $caps,string $cap,int $userId,array $args): array
    {
        if(!in_array($cap,['edit_post','delete_post','read_post'],true)||empty($args[0])){return $caps;}
        $user=get_userdata($userId);
        if(!$user||!in_array('serben_lojista',(array)$user->roles,true)){return $caps;}
        $postId=(int)$args[0];
        $post=get_post($postId);
        $postType=sanitize_key((string)Settings::get('partners_post_type','serben_parceiro'));
        if(!$post||$post->post_type!==$postType){return $caps;}
        $linked=(int)get_user_meta($userId,'serben_partner_post_id',true);
        if ($linked !== $postId || !$this->matchesCnpj($userId, $postId)) { return ['do_not_allow']; }
        if ($cap === 'read_post') { return ['read']; }
        if ($cap === 'edit_post') { return ['edit_serben_partner']; }
        return ['do_not_allow'];
    }

    private function matchesCnpj(int $userId, int $postId): bool
    {
        $approvedCnpj = preg_replace('/\D+/', '', (string)get_user_meta($userId, 'serben_partner_cnpj', true));
        if (strlen($approvedCnpj) !== 14) { return false; }
        foreach (['cnpj_do_parceiro','_serben_cnpj'] as $field) {
            $onPost = preg_replace('/\D+/', '', (string)get_post_meta($postId, $field, true));
            if ($onPost !== '' && $onPost === $approvedCnpj) { return true; }
        }
        return false;
    }

    public function restrictAdminList($query): void
    {
        if(!is_admin()||!$query->is_main_query()){return;}
        $user=wp_get_current_user();
        if(!in_array('serben_lojista',(array)$user->roles,true)){return;}
        $postType=sanitize_key((string)Settings::get('partners_post_type','serben_parceiro'));
        if(($query->get('post_type')?:'')!==$postType){return;}
        $linked=(int)get_user_meta($user->ID,'serben_partner_post_id',true);
        $query->set('post__in', $linked && $this->matchesCnpj((int)$user->ID,$linked) ? [$linked] : [0]);
    }

    public function guardPostAccess(): void
    {
        if(!is_admin()||!isset($_GET['post'])){return;}
        $user=wp_get_current_user();
        if(!in_array('serben_lojista',(array)$user->roles,true)){return;}
        $postId=absint($_GET['post']);
        $post=get_post($postId);
        $postType=sanitize_key((string)Settings::get('partners_post_type','serben_parceiro'));
        if($post&&$post->post_type===$postType&&((int)get_user_meta($user->ID,'serben_partner_post_id',true)!==$postId || !$this->matchesCnpj((int)$user->ID,$postId))){wp_die('Você não pode acessar este parceiro.');}
    }
}
