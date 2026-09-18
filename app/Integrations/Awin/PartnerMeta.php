<?php
namespace SerbenConnect\Integrations\Awin;
use SerbenConnect\Providers\PartnersProvider;
if (!defined('ABSPATH')) { exit; }
final class PartnerMeta
{
    public function register(): void { add_action('add_meta_boxes',[$this,'box']); add_action('save_post',[$this,'save']); }
    public function box(): void { add_meta_box('serben-awin','Serben Connect — Awin',[$this,'render'],PartnersProvider::postType(),'side','default'); }
    public function render($post): void
    {
        wp_nonce_field('serben_awin_partner_meta','serben_awin_partner_nonce');
        $id=get_post_meta($post->ID,'awin_advertiser_id',true); $enabled=get_post_meta($post->ID,'awin_cashback_enabled',true); $pct=get_post_meta($post->ID,'awin_cashback_percent',true);
        echo '<p><label><strong>Advertiser ID Awin</strong><br><input type="number" name="serben_awin_advertiser_id" value="'.esc_attr($id).'" style="width:100%"></label></p>';
        echo '<p><label><input type="checkbox" name="serben_awin_cashback_enabled" value="1" '.checked($enabled,'1',false).'> Cashback Awin ativo</label></p>';
        echo '<p><label><strong>Cashback do associado (%)</strong><br><input type="number" min="0" step="0.01" name="serben_awin_cashback_percent" value="'.esc_attr($pct).'" style="width:100%"></label></p>';
        $status = (string) get_post_meta($post->ID,'awin_program_status',true);
        $state = (string) get_post_meta($post->ID,'awin_sync_state',true);
        echo '<p class="description">Percentual aplicado sobre o valor da venda aprovado pela Awin.</p>';
        if ($id) { echo '<p><strong>Status Awin:</strong> ' . esc_html($status !== '' ? $status : '—') . '<br><strong>Estado sincronizado:</strong> ' . esc_html($state !== '' ? $state : '—') . '</p>'; }
    }
    public function save(int $postId): void
    {
        if (!isset($_POST['serben_awin_partner_nonce']) || !wp_verify_nonce($_POST['serben_awin_partner_nonce'],'serben_awin_partner_meta') || !current_user_can('edit_post',$postId)) return;
        update_post_meta($postId,'awin_advertiser_id',absint($_POST['serben_awin_advertiser_id']??0));
        update_post_meta($postId,'awin_cashback_enabled',!empty($_POST['serben_awin_cashback_enabled'])?'1':'0');
        $pct=max(0,(float)str_replace(',','.',(string)($_POST['serben_awin_cashback_percent']??'0'))); update_post_meta($postId,'awin_cashback_percent',$pct);
    }
}
