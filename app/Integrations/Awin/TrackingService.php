<?php
namespace SerbenConnect\Integrations\Awin;
use SerbenConnect\Support\Settings;
if (!defined('ABSPATH')) { exit; }
final class TrackingService
{
    private $client;
    public function __construct(?Client $client=null) { $this->client=$client?:new Client(); }
    public function register(): void { add_action('admin_post_serben_awin_redirect',[$this,'redirect']); add_action('admin_post_nopriv_serben_awin_redirect',[$this,'redirect']); }
    public function url(int $partnerId,string $destination=''): string {
        return wp_nonce_url(admin_url('admin-post.php?action=serben_awin_redirect&partner_id='.$partnerId.($destination!==''?'&destination='.rawurlencode($destination):'')),'serben_awin_redirect_'.$partnerId);
    }
    private function failure(int $partnerId, int $advertiserId, string $reason, int $httpCode=0): void {
        $events=get_option('serben_awin_tracking_failures',[]);
        if(!is_array($events)) $events=[];
        array_unshift($events,['at'=>current_time('mysql'),'partner_id'=>$partnerId,'advertiser_id'=>$advertiserId,'user_id'=>get_current_user_id(),'reason'=>$reason,'http_code'=>$httpCode]);
        update_option('serben_awin_tracking_failures',array_slice($events,0,30),false);
    }
    public function redirect(): void
    {
        $partnerId=absint($_GET['partner_id']??0);
        if(!$partnerId || !wp_verify_nonce((string)($_GET['_wpnonce']??''),'serben_awin_redirect_'.$partnerId)) wp_die('Link inválido.');
        if(!is_user_logged_in()) { wp_safe_redirect(wp_login_url($this->url($partnerId))); exit; }
        if(get_post_type($partnerId)!=='serben_parceiro') wp_die('Parceiro inválido.');
        $aid=(int)get_post_meta($partnerId,'awin_advertiser_id',true);
        if(!$aid) wp_die('Parceiro sem vínculo Awin.');
        $destination=esc_url_raw((string)($_GET['destination']??''));
        if($destination==='') $destination=(string)get_post_meta($partnerId,'site',true);
        // O app SerBen exige o CPF de 11 digitos no ClickRef AWIN.
        // Nunca prosseguir sem um CPF valido, evitando atribuicao incorreta.
        $clickRef=$this->cpfForUser(get_current_user_id());
        if($clickRef==='') {
            $this->failure($partnerId,$aid,'missing_or_invalid_cpf');
            wp_die('Seu cadastro não possui CPF válido para rastreamento AWIN. Atualize seus dados antes de comprar.','CPF necessário',['response'=>422]);
        }
        $publisher=(string)Settings::get('awin_publisher_id');
        $body=['advertiserId'=>$aid,'parameters'=>['clickref'=>$clickRef,'campaign'=>'serben-connect'],'shorten'=>false];
        if($destination!=='') $body['destinationUrl']=$destination;
        $res=$this->client->post('publishers/'.$publisher.'/linkbuilder/generate',$body);
        $tracking=is_array($res['body']??null)?(string)($res['body']['url']??''):'';
        $httpCode=(int)($res['code']??0);
        if(empty($res['ok']) || $tracking==='') {
            $this->failure($partnerId,$aid,'linkbuilder_failed',$httpCode);
            wp_die('Não foi possível gerar um link de compra rastreável neste momento. Nenhuma compra foi iniciada. Tente novamente mais tarde.','Rastreamento indisponível',['response'=>503]);
        }
        // A resposta precisa conter a referência individual. Links de fallback não garantem atribuição.
        if(strpos(rawurldecode(html_entity_decode($tracking,ENT_QUOTES,'UTF-8')),$clickRef)===false) {
            $this->failure($partnerId,$aid,'clickref_missing_from_link',$httpCode);
            wp_die('O link retornado pela AWIN não confirmou a referência do associado. Compra interrompida para evitar perda de rastreamento.','Rastreamento indisponível',['response'=>503]);
        }
        $scheme=strtolower((string)wp_parse_url($tracking,PHP_URL_SCHEME));
        if(!in_array($scheme,['http','https'],true)) {
            $this->failure($partnerId,$aid,'invalid_tracking_url',$httpCode);
            wp_die('Link de rastreamento inválido.','Rastreamento indisponível',['response'=>503]);
        }
        global $wpdb;
        // O CPF e estavel e pode ser reutilizado em varios cliques. A tabela legada
        // tem UNIQUE(click_ref), por isso nao sobrescrever o vinculo original.
        $existingUser=(int)$wpdb->get_var($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}serben_awin_clicks WHERE click_ref=%s LIMIT 1",$clickRef));
        if($existingUser>0 && $existingUser!==get_current_user_id()) {
            $this->failure($partnerId,$aid,'cpf_already_linked_to_another_user',$httpCode);
            wp_die('CPF vinculado a outro cadastro. Entre em contato com o suporte.','Rastreamento indisponível',['response'=>409]);
        }
        if($existingUser===0) {
            $saved=$wpdb->insert($wpdb->prefix.'serben_awin_clicks',['click_ref'=>$clickRef,'user_id'=>get_current_user_id(),'partner_id'=>$partnerId,'advertiser_id'=>$aid,'destination_url'=>$destination,'tracking_url'=>$tracking,'created_at'=>current_time('mysql')],['%s','%d','%d','%d','%s','%s','%s']);
            if(!$saved) {
                $this->failure($partnerId,$aid,'click_database_insert_failed',$httpCode);
                wp_die('Não foi possível registrar o CPF para rastreamento. Compra interrompida.','Rastreamento indisponível',['response'=>503]);
            }
        }
        // Preserve only the club origin (not the CPF-bearing tracking URL) as HTTP Referer.
        // Browser/privacy settings and AWIN attribution can still suppress publisherUrl.
        if (!headers_sent()) { header('Referrer-Policy: origin'); }
        wp_redirect($tracking, 302, 'SerBen Connect'); exit;
    }
    private function cpfForUser(int $userId): string {
        foreach(['serben_documento','serben_cpf','cpf','billing_cpf'] as $key) {
            $cpf=preg_replace('/\D+/','',(string)get_user_meta($userId,$key,true));
            if($this->validCpf($cpf)) return $cpf;
        }
        return '';
    }
    private function validCpf(string $cpf): bool {
        if(!preg_match('/^[0-9]{11}$/D',$cpf) || preg_match('/^(\d)\1{10}$/D',$cpf)) return false;
        for($digit=9;$digit<11;$digit++) {
            $sum=0;
            for($i=0;$i<$digit;$i++) $sum+=(int)$cpf[$i]*($digit+1-$i);
            $check=($sum*10)%11;
            if($check===10) $check=0;
            if($check!==(int)$cpf[$digit]) return false;
        }
        return true;
    }

}
