<?php
namespace SerbenConnect\Integrations\Awin;
use SerbenConnect\Services\FidelidadeService; use SerbenConnect\Support\Settings;
if(!defined('ABSPATH')){exit;}
final class TransactionSync
{
    private $client; public function __construct(?Client $client=null,?FidelidadeService $f=null){$this->client=$client?:new Client();}
    public function sync(): array
    {
        $pub = (string) Settings::get('awin_publisher_id');
        $days = max(30, min(90, (int) Settings::get('awin_transaction_days', 30)));
        // Recheck pending commissions after initial purchase; capped to limit API load.
        $end = gmdate('Y-m-d\TH:i:s');
        $start = gmdate('Y-m-d\TH:i:s', time() - $days * DAY_IN_SECONDS);
        if ($pub === '' || $start === '' || $end === '') {
            return ['ok'=>false, 'error'=>'Parâmetros de consulta AWIN incompletos'];
        }
        $res=$this->client->get('publishers/'.$pub.'/transactions/',['startDate'=>$start,'endDate'=>$end,'timezone'=>'UTC','dateType'=>'transaction']); if(empty($res['ok'])||!is_array($res['body'])) { $failure=['ok'=>false,'error'=>'Falha na consulta de transações AWIN','http_code'=>(int)($res['code']??0)]; update_option('serben_awin_last_transaction_sync',['at'=>current_time('mysql'),'source'=>wp_doing_cron()?'cron':'manual','result'=>$failure],false); return $failure; }
        $items=$res['body']['transactions']??$res['body']['pageItems']??$res['body']; if(!is_array($items))$items=[]; $seen=0;$credited=0;$manual=0;$errors=0;
        foreach($items as $tx){ if(!is_array($tx))continue; $txid=(string)($tx['transactionId']??$tx['id']??$tx['transactionID']??''); if($txid==='')continue; $seen++; $existing=$this->existing($txid);
            $clickRef=$this->extractClickRef($tx);
            // Do not erase an already linked transaction when AWIN omits clickRefs.
            if($clickRef==='' && $existing) $clickRef=(string)($existing['click_ref']??'');
            $status=strtolower(trim((string)($tx['commissionStatus']??$tx['status']??$tx['transactionStatus']??'')));
            if($status==='' && $existing) $status=(string)($existing['status']??'');
            $sale=$this->amount($tx['saleAmount']??$tx['sale_amount']??$tx['amount']??null); $currency=$this->currency($tx['saleAmount']??null,$tx);
            $mapping=$this->click($clickRef);
            $userId=(int)($mapping['user_id']??0); $partnerId=(int)($mapping['partner_id']??0);
            // Keep previously verified association when AWIN does not return a reference.
            if($userId<=0 && $existing && (string)($existing['click_ref']??'')===$clickRef) $userId=(int)($existing['user_id']??0);
            if($partnerId<=0 && $existing && (string)($existing['click_ref']??'')===$clickRef) $partnerId=(int)($existing['partner_id']??0); $aid=(int)($tx['advertiserId']??$mapping['advertiser_id']??0);
            // Para ClickRef=CPF, o mesmo associado compra em diferentes anunciantes.
            // Identificar o parceiro pelo advertiserId, nunca pelo primeiro clique do CPF.
            if(preg_match('/^[0-9]{11}$/D',$clickRef) && $aid) $partnerId=$this->partner($aid);
            if(!$partnerId&&$aid)$partnerId=$this->partner($aid); $pct=$partnerId?(float)get_post_meta($partnerId,'awin_cashback_percent',true):0; $enabled=$partnerId?get_post_meta($partnerId,'awin_cashback_enabled',true)==='1':false; $cashbackCents=(int)round($sale*100*$pct/100);
            $serbenStatus='waiting'; $serbenResponse='';
            if($existing&&($existing['serben_status']??'')==='credited'){ $this->upsert($txid,$aid,$partnerId,$userId,$clickRef,$status,$currency,$sale,$pct,$cashbackCents,'credited',(string)$existing['serben_response'],$tx); continue; }
            // Credit ownership belongs exclusively to the SerBen app.
            // Never invoke FidelidadeService or any financial write endpoint here.
            if($status==='approved'&&$enabled&&$pct>0&&$cashbackCents>0&&$userId>0){
                $serbenStatus='external_app';
            } elseif($status==='declined'||$status==='deleted'){$serbenStatus='declined';}
            elseif($status==='approved'){
                if($clickRef==='' || !$mapping){$serbenStatus='missing_click';}
                elseif($userId<=0){$serbenStatus='missing_user';}
                elseif(!$enabled){$serbenStatus='cashback_disabled';}
                elseif($pct<=0){$serbenStatus='missing_percent';}
                elseif($cashbackCents<=0){$serbenStatus='zero_cashback';}
            } elseif($status==='pending'){$serbenStatus='pending';}
            $this->upsert($txid,$aid,$partnerId,$userId,$clickRef,$status,$currency,$sale,$pct,$cashbackCents,$serbenStatus,$serbenResponse,$tx);
        }
        $result=['ok'=>true,'transactions'=>$seen,'credited'=>0,'manual'=>$manual,'errors'=>$errors,'credit_mode'=>'serben_app_only'];
        update_option('serben_awin_last_transaction_sync', ['at'=>current_time('mysql'), 'source'=>wp_doing_cron()?'cron':'manual', 'result'=>$result], false);
        return $result;
    }
    /** AWIN returns clickRefs as a keyed object or list; accept legacy SerBen references and new 11-digit CPF references. */
    private function extractClickRef(array $tx): string
    {
        $candidates=[];
        foreach(['clickRefs','clickRef','clickref','clickReference'] as $key){
            if(array_key_exists($key,$tx)) $this->collectClickRefs($tx[$key],$candidates);
        }
        foreach($candidates as $candidate){
            $candidate=trim((string)$candidate);
            if(preg_match('/^sbn_[A-Za-z0-9_-]{8,100}$/D',$candidate) || preg_match('/^[0-9]{11}$/D',$candidate)) return $candidate;
        }
        return '';
    }
    private function collectClickRefs($value,array &$result): void
    {
        if(is_string($value)||is_numeric($value)){$result[]=(string)$value;return;}
        if(!is_array($value))return;
        foreach($value as $key=>$item){
            if(is_array($item))$this->collectClickRefs($item,$result);
            elseif(is_string($item)||is_numeric($item))$result[]=(string)$item;
        }
    }
    private function amount($v): float { if(is_array($v))return (float)($v['amount']??$v['value']??0); return (float)$v; }
    private function currency($amount,array $tx): string { return is_array($amount)?(string)($amount['currency']??''):(string)($tx['currency']??$tx['currencyCode']??''); }
    private function document(int $uid): string { foreach(['serben_documento','serben_cpf','cpf','billing_cpf'] as $k){$v=preg_replace('/\D+/','',(string)get_user_meta($uid,$k,true)); if(strlen($v)===11)return $v;} return ''; }
    private function click(string $ref): array { if($ref==='')return[]; global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}serben_awin_clicks WHERE click_ref=%s LIMIT 1",$ref),ARRAY_A); return is_array($r)?$r:[]; }
    private function partner(int $aid): int { $ids=get_posts(['post_type'=>\SerbenConnect\Providers\PartnersProvider::postType(),'post_status'=>'any','fields'=>'ids','posts_per_page'=>1,'meta_key'=>'awin_advertiser_id','meta_value'=>$aid]); return $ids?(int)$ids[0]:0; }
    private function existing(string $txid): array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}serben_awin_transactions WHERE awin_transaction_id=%s LIMIT 1",$txid),ARRAY_A); return is_array($r)?$r:[]; }
    private function upsert($txid,$aid,$pid,$uid,$ref,$status,$currency,$sale,$pct,$cents,$ss,$resp,array $tx): void { global $wpdb; $date=(string)($tx['transactionDate']??$tx['transaction_date']??''); $date=$date?gmdate('Y-m-d H:i:s',strtotime($date)):null; $data=['awin_transaction_id'=>$txid,'advertiser_id'=>$aid?:null,'partner_id'=>$pid?:null,'user_id'=>$uid?:null,'click_ref'=>$ref,'status'=>$status,'currency'=>$currency,'sale_amount'=>$sale,'cashback_percent'=>$pct,'cashback_cents'=>$cents,'serben_status'=>$ss,'serben_response'=>$resp,'transaction_date'=>$date,'updated_at'=>current_time('mysql')]; $existing=$this->existing($txid); if($existing)$wpdb->update($wpdb->prefix.'serben_awin_transactions',$data,['id'=>(int)$existing['id']]); else $wpdb->insert($wpdb->prefix.'serben_awin_transactions',$data); }
}
