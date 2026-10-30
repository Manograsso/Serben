<?php
defined( 'ABSPATH' ) || exit;
final class Serben_Media_Repair {
    public function __construct(){ add_action('admin_post_serben_repair_partner_media',array($this,'run')); add_action('admin_post_serben_cleanup_partner_logos',array($this,'cleanup_logos')); }
    public function run(): void {
        if(!current_user_can('manage_options')) wp_die('Permissão insuficiente.');
        check_admin_referer('serben_repair_partner_media');
        $ids=get_posts(array('post_type'=>SERBEN_POST_TYPE,'post_status'=>array('publish','draft','pending','private'),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
        $covers=0;$logos=0;
        foreach($ids as $id){
            $cid=absint(get_post_meta($id,Serben_Cover_Studio::META_COVER_ID,true));
            if($cid&&!wp_get_attachment_url($cid)){delete_post_meta($id,Serben_Cover_Studio::META_COVER_ID);delete_post_meta($id,Serben_Cover_Studio::META_SOURCE_URL);delete_post_meta($id,Serben_Cover_Studio::META_SOURCE_LABEL);delete_post_meta($id,Serben_Cover_Studio::META_CREDIT);$covers++;}
            $lid=absint(get_post_meta($id,SERBEN_META_PREFIX.'logo_id',true));
            if($lid&&!wp_get_attachment_url($lid)){delete_post_meta($id,SERBEN_META_PREFIX.'logo_id');$logos++;}
        }
        $url=add_query_arg('serben_cover_notice',rawurlencode(sprintf('Revisão concluída: %d capas inválidas e %d logos inválidos foram desvinculados.',$covers,$logos)),admin_url('edit.php?post_type='.SERBEN_POST_TYPE.'&page=serben-maintenance'));
        wp_safe_redirect($url);exit;
    }

    public function cleanup_logos(): void {
        if(!current_user_can('manage_options')) wp_die('Permissão insuficiente.');
        check_admin_referer('serben_cleanup_partner_logos');
        $partners=get_posts(array('post_type'=>SERBEN_POST_TYPE,'post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
        $used=array();
        foreach($partners as $pid){
            $lid=absint(get_post_meta($pid,SERBEN_META_PREFIX.'logo_id',true)); if($lid)$used[$lid]=true;
            foreach(array(SERBEN_META_PREFIX.'banner_id',Serben_Cover_Studio::META_COVER_ID) as $key){$mid=absint(get_post_meta($pid,$key,true));if($mid)$used[$mid]=true;}
            $gallery=(string)get_post_meta($pid,SERBEN_META_PREFIX.'gallery_ids',true);foreach(array_filter(array_map('absint',preg_split('/\s*,\s*/',$gallery))) as $gid)$used[$gid]=true;
            $thumb=get_post_thumbnail_id($pid);if($thumb)$used[$thumb]=true;
        }
        $attachments=get_posts(array('post_type'=>'attachment','post_status'=>'inherit','posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_serben_asset_type','meta_value'=>'logo','no_found_rows'=>true));
        $deleted=0;
        foreach($attachments as $aid){if(empty($used[$aid])&&wp_delete_attachment($aid,true))$deleted++;}
        Serben_Utils::clear_query_cache();
        $url=add_query_arg('serben_notice',rawurlencode(sprintf('Limpeza concluída: %d logos antigos gerenciados pelo plugin foram apagados.',$deleted)),admin_url('edit.php?post_type='.SERBEN_POST_TYPE.'&page=serben-maintenance'));
        wp_safe_redirect($url);exit;
    }
}
