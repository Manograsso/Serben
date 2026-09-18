<?php
namespace SerbenConnect\Integrations\Awin;
use SerbenConnect\Services\FidelidadeService; use SerbenConnect\Support\Settings;
if(!defined('ABSPATH')){exit;}
final class TransactionSync
{
    private $client; private $fidelidade; public function __construct(?Client $client=null,?FidelidadeService $f=null){$this->client=$client?:new Client();$this->fidelidade=$f?:new FidelidadeService();}
    public function sync(): array
    {
        $pub=(string)Settings::get('awin_publisher_id'); $days=(int)Settings::get('awin_transaction_days',7); $end=gmdate('Y-m-d\TH:i:s'); $start=gmdate('Y-m-d\TH:i:s',time()-$days*DAY_IN_SECONDS);
        $res=$this->client->get('publishers/'.$pub.'/transactions/',['startDate'=>$start,'endDate'=>$end,'timezone'=>'UTC','dateType'=>'transaction']); if(empty($res['ok'])||!is_array($res['body'])) return ['ok'=>false,'response'=>$res];
        $items=$res['body']['transactions']??$res['body']['pageItems']??$res['body']; if(!is_array($items))$items=[]; $seen=0;$credited=0;$manual=0;
        foreach($items as $tx){ if(!is_array($tx))continue; $txid=(string)($tx['transactionId']??$tx['id']??$tx['transactionID']??''); if($txid==='')continue; $seen++; $clickRef=(string)($tx['clickRef']??$tx['clickref']??''); $status=strtolower((string)($tx['status']??$tx['transactionStatus']??''));
            $sale=$this->amount($tx['saleAmount']??$tx['sale_amount']??$tx['amount']??null); $currency=$this->currency($tx['saleAmount']??null,$tx);
            $mapping=$this->click($clickRef); $userId=(int)($mapping['user_id']??0); $partnerId=(int)($mapping['partner_id']??0); $aid=(int)($tx['advertiserId']??$mapping['advertiser_id']??0);
            if(!$partnerId&&$aid)$partnerId=$this->partner($aid); $pct=$partnerId?(float)get_post_meta($partnerId,'awin_cashback_percent',true):0; $enabled=$partnerId?get_post_meta($partnerId,'awin_cashback_enabled',true)==='1':false; $cashbackCents=(int)round($sale*100*$pct/100);
            $serbenStatus='waiting'; $serbenResponse='';
            $existing=$this->existing($txid); if($existing&&($existing['serben_status']??'')==='credited'){ $this->upsert($txid,$aid,$partnerId,$userId,$clickRef,$status,$currency,$sale,$pct,$cashbackCents,'credited',(string)$existing['serben_response'],$tx); continue; }
            if($status==='approved'&&$enabled&&$pct>0&&$cashbackCents>0&&$userId>0){
                if(strtoupper($currency)!==strtoupper((string)Settings::get('awin_credit_currency','BRL'))){$serbenStatus='manual_currency';$manual++;}
                else { $cpf=$this->document($userId); if($cpf!==''){ $credit=$this->fidelidade->registrarCashbackExterno($cpf,(int)round($sale*100),$cashbackCents); $serbenResponse=wp_json_encode($credit); if(!empty($credit['ok'])){$serbenStatus='credited';$credited++;}else{$serbenStatus='error';} } else {$serbenStatus='missing_document';} }
            } elseif($status==='declined'||$status==='deleted'){$serbenStatus='declined';}
            $this->upsert($txid,$aid,$partnerId,$userId,$clickRef,$status,$currency,$sale,$pct,$cashbackCents,$serbenStatus,$serbenResponse,$tx);
        }
        return ['ok'=>true,'transactions'=>$seen,'credited'=>$credited,'manual'=>$manual];
    }
    private function amount($v): float { if(is_array($v))return (float)($v['amount']??$v['value']??0); return (float)$v; }
    private function currency($amount,array $tx): string { return is_array($amount)?(string)($amount['currency']??''):(string)($tx['currency']??$tx['currencyCode']??''); }
    private function document(int $uid): string { foreach(['serben_documento','serben_cpf','cpf','billing_cpf'] as $k){$v=preg_replace('/\D+/','',(string)get_user_meta($uid,$k,true)); if(strlen($v)===11)return $v;} return ''; }
    private function click(string $ref): array { if($ref==='')return[]; global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}serben_awin_clicks WHERE click_ref=%s LIMIT 1",$ref),ARRAY_A); return is_array($r)?$r:[]; }
    private function partner(int $aid): int { $ids=get_posts(['post_type'=>\SerbenConnect\Providers\PartnersProvider::postType(),'post_status'=>'any','fields'=>'ids','posts_per_page'=>1,'meta_key'=>'awin_advertiser_id','meta_value'=>$aid]); return $ids?(int)$ids[0]:0; }
    private function existing(string $txid): array { global $wpdb; $r=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}serben_awin_transactions WHERE awin_transaction_id=%s LIMIT 1",$txid),ARRAY_A); return is_array($r)?$r:[]; }
    private function upsert($txid,$aid,$pid,$uid,$ref,$status,$currency,$sale,$pct,$cents,$ss,$resp,array $tx): void { global $wpdb; $date=(string)($tx['transactionDate']??$tx['transaction_date']??''); $date=$date?gmdate('Y-m-d H:i:s',strtotime($date)):null; $data=['awin_transaction_id'=>$txid,'advertiser_id'=>$aid?:null,'partner_id'=>$pid?:null,'user_id'=>$uid?:null,'click_ref'=>$ref,'status'=>$status,'currency'=>$currency,'sale_amount'=>$sale,'cashback_percent'=>$pct,'cashback_cents'=>$cents,'serben_status'=>$ss,'serben_response'=>$resp,'transaction_date'=>$date,'updated_at'=>current_time('mysql')]; $existing=$this->existing($txid); if($existing)$wpdb->update($wpdb->prefix.'serben_awin_transactions',$data,['id'=>(int)$existing['id']]); else $wpdb->insert($wpdb->prefix.'serben_awin_transactions',$data); }
}
