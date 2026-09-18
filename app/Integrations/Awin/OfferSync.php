<?php
namespace SerbenConnect\Integrations\Awin;
use SerbenConnect\Providers\PartnersProvider; use SerbenConnect\Support\Settings;
if(!defined('ABSPATH')){exit;}
final class OfferSync
{
    private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
    public function sync(): array
    {
        $publisher=(string)Settings::get('awin_publisher_id'); $page=1;$total=0;$updated=[];
        do { $res=$this->client->post('publisher/'.$publisher.'/promotions',['filters'=>['membership'=>'joined','status'=>'active','type'=>'all'],'pagination'=>['page'=>$page,'pageSize'=>200]]); if(empty($res['ok'])||!is_array($res['body'])) break;
            $body=$res['body']; $offers=$body['promotions']??$body['pageItems']??$body['offers']??(isset($body[0])?$body:[]); if(!is_array($offers))$offers=[];
            foreach($offers as $offer){ if(!is_array($offer))continue; $aid=(int)($offer['advertiser']['id']??$offer['advertiserId']??0); if(!$aid)continue; $pid=$this->find($aid); if(!$pid)continue; $list=get_post_meta($pid,'awin_offers',true); if(!is_array($list))$list=[]; $key=(string)($offer['promotionId']??md5(wp_json_encode($offer))); $list[$key]=$offer; update_post_meta($pid,'awin_offers',$list); update_post_meta($pid,'awin_last_offer_sync',current_time('mysql')); $updated[$pid]=1;$total++; }
            $totalPages=(int)($body['pagination']['totalPages']??$body['totalPages']??1); $page++;
        } while($page<=$totalPages && $page<=20);
        return ['ok'=>true,'offers'=>$total,'partners'=>count($updated)];
    }
    private function find(int $aid): int { $ids=get_posts(['post_type'=>PartnersProvider::postType(),'post_status'=>'any','fields'=>'ids','posts_per_page'=>1,'meta_key'=>'awin_advertiser_id','meta_value'=>$aid]); return $ids?(int)$ids[0]:0; }
}
