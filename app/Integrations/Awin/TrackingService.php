<?php
namespace SerbenConnect\Integrations\Awin;
use SerbenConnect\Support\Settings;
if(!defined('ABSPATH')){exit;}
final class TrackingService
{
    private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
    public function register(): void { add_action('admin_post_serben_awin_redirect',[$this,'redirect']); add_action('admin_post_nopriv_serben_awin_redirect',[$this,'redirect']); }
    public function url(int $partnerId,string $destination=''): string { return wp_nonce_url(admin_url('admin-post.php?action=serben_awin_redirect&partner_id='.$partnerId.($destination!==''?'&destination='.rawurlencode($destination):'')),'serben_awin_redirect_'.$partnerId); }
    public function redirect(): void
    {
        $partnerId=absint($_GET['partner_id']??0); if(!$partnerId||!wp_verify_nonce((string)($_GET['_wpnonce']??''),'serben_awin_redirect_'.$partnerId)) wp_die('Link inválido.');
        if(!is_user_logged_in()) { wp_safe_redirect(wp_login_url($this->url($partnerId))); exit; }
        $aid=(int)get_post_meta($partnerId,'awin_advertiser_id',true); if(!$aid) wp_die('Parceiro sem vínculo Awin.');
        $destination=esc_url_raw((string)($_GET['destination']??'')); if($destination==='')$destination=(string)get_post_meta($partnerId,'site',true);
        $clickRef='sbn_'.wp_generate_password(24,false,false); $publisher=(string)Settings::get('awin_publisher_id');
        $body=['advertiserId'=>$aid,'parameters'=>['clickref'=>$clickRef,'campaign'=>'serben-connect'],'shorten'=>false]; if($destination!=='')$body['destinationUrl']=$destination;
        $res=$this->client->post('publishers/'.$publisher.'/linkbuilder/generate',$body); $tracking=is_array($res['body']??null)?(string)($res['body']['url']??''):'';
        if(empty($res['ok'])||$tracking==='') { $tracking=(string)get_post_meta($partnerId,'awin_click_through_url',true); if($tracking==='')$tracking=(string)get_post_meta($partnerId,'link_afiliado',true); }
        if($tracking==='') wp_die('Não foi possível gerar o link Awin.');
        global $wpdb; $wpdb->insert($wpdb->prefix.'serben_awin_clicks',['click_ref'=>$clickRef,'user_id'=>get_current_user_id(),'partner_id'=>$partnerId,'advertiser_id'=>$aid,'destination_url'=>$destination,'tracking_url'=>$tracking,'created_at'=>current_time('mysql')],['%s','%d','%d','%d','%s','%s','%s']);
        wp_redirect($tracking); exit;
    }
}
