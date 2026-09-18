<?php
namespace SerbenConnect\API;

use SerbenConnect\Support\Logger;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

class Client
{
    /** Legacy API: kept only for services not yet migrated by Serben (notably Fidelidade). */
    public function get(string $endpoint, array $query = [], bool $sendIdentifier = true): array { return $this->request('GET', $endpoint, $query, [], $sendIdentifier); }
    public function post(string $endpoint, array $body = [], bool $sendIdentifier = true): array { return $this->request('POST', $endpoint, [], $body, $sendIdentifier); }

    /** New official /api/integracao/* API. Store credentials infer id_loja; callers must not add it. */
    public function integrationGet(string $module, string $endpoint, array $query = []): array { return $this->integrationRequest('GET', $module, $endpoint, $query, []); }
    public function integrationPost(string $module, string $endpoint, array $body = [], array $extraHeaders = []): array { return $this->integrationRequest('POST', $module, $endpoint, [], $body, $extraHeaders); }
    public function integrationPut(string $module, string $endpoint, array $body = []): array { return $this->integrationRequest('PUT', $module, $endpoint, [], $body); }

    public function healthCheck(): array
    {
        return $this->integrationRequest('GET', 'status', 'Health/check', [], [], [], true);
    }

    public function integrationRequest(string $method, string $module, string $endpoint, array $query = [], array $body = [], array $extraHeaders = [], bool $health = false): array
    {
        $settings = Settings::all();
        $base = rtrim((string)($settings['base_url'] ?? ''), '/');
        $module = trim($module, '/'); $endpoint = ltrim($endpoint, '/');
        $url = $base . '/api/integracao/' . $module . '/' . $endpoint;
        if ($query) { $url = add_query_arg($query, $url); }

        $apiKey = trim((string)($settings['api_key'] ?? ''));
        $identifier = trim((string)($settings['identifier'] ?? ''));
        $headers = ['x-api-key'=>$apiKey, 'identifier'=>$identifier, 'Accept'=>'application/json'];
        if (strtoupper($method) !== 'GET') { $headers['Content-Type'] = 'application/json'; }
        foreach ($extraHeaders as $name=>$value) { if ($value !== '' && $value !== null) { $headers[$name] = $value; } }

        $args = ['method'=>strtoupper($method), 'timeout'=>30, 'headers'=>$headers];
        if (strtoupper($method) !== 'GET') { $args['body'] = wp_json_encode($body); }
        $start=microtime(true); $response=wp_remote_request($url,$args); $duration=round((microtime(true)-$start)*1000);
        $safeHeaders=$headers; foreach(['x-api-key','identifier'] as $h){if(!empty($safeHeaders[$h]))$safeHeaders[$h]='***';}
        $context=['url'=>$url,'headers'=>$safeHeaders,'duration_ms'=>$duration,'api_generation'=>'integration'];

        if(is_wp_error($response)){
            $message=$this->redactSecrets($response->get_error_message(),[$apiKey,$identifier]);
            Logger::add('error',$method,$module.'/'.$endpoint,0,$message,$context);
            return ['ok'=>false,'code'=>0,'body'=>null,'raw'=>$message,'url'=>$url,'duration_ms'=>$duration,'rate_limited'=>false];
        }
        $code=(int)wp_remote_retrieve_response_code($response); $raw=(string)wp_remote_retrieve_body($response);
        $safeRaw=$this->redactSecrets($raw,[$apiKey,$identifier]); $decoded=json_decode($safeRaw,true); $decoded=json_last_error()===JSON_ERROR_NONE?$decoded:$safeRaw;
        $ok=$code>=200&&$code<300; $retryAfter=(string)wp_remote_retrieve_header($response,'retry-after');
        Logger::add($ok?'info':'error',$method,$module.'/'.$endpoint,$code,$health?'Serben Health Check':'Serben Integration API',$context+['rate_limited'=>$code===429,'retry_after'=>$retryAfter,'response'=>is_string($decoded)?mb_substr($decoded,0,2000):$decoded]);
        return ['ok'=>$ok,'code'=>$code,'body'=>$decoded,'raw'=>$safeRaw,'url'=>$url,'duration_ms'=>$duration,'rate_limited'=>$code===429,'retry_after'=>$retryAfter];
    }

    /** Legacy endpoint request. Do not migrate Fidelidade until Serben publishes replacement routes. */
    public function request(string $method, string $endpoint, array $query = [], array $body = [], bool $sendIdentifier = true): array
    {
        $settings=Settings::all(); $base=rtrim((string)($settings['base_url']??''),'/'); $endpoint=ltrim($endpoint,'/');
        $url=$base.'/api/index.php/api/'.$endpoint; if($query)$url=add_query_arg($query,$url);
        $apiKey=trim((string)($settings['api_key']??'')); $identifier=trim((string)($settings['identifier']??''));
        $headers=['X-API-KEY'=>$apiKey,'Accept'=>'application/json','Content-Type'=>'application/json'];
        if($sendIdentifier&&$identifier!=='')$headers['IDENTIFIER']=$identifier;
        $args=['method'=>strtoupper($method),'timeout'=>30,'headers'=>$headers]; if(strtoupper($method)!=='GET')$args['body']=wp_json_encode($body);
        $start=microtime(true);$response=wp_remote_request($url,$args);$duration=round((microtime(true)-$start)*1000);
        $safeHeaders=$headers;if(!empty($safeHeaders['X-API-KEY']))$safeHeaders['X-API-KEY']='***';if(!empty($safeHeaders['IDENTIFIER']))$safeHeaders['IDENTIFIER']='***';
        $context=['url'=>$url,'headers'=>$safeHeaders,'duration_ms'=>$duration,'api_generation'=>'legacy'];
        if(is_wp_error($response)){Logger::add('error',$method,$endpoint.$this->queryString($query),0,$response->get_error_message(),$context);return['ok'=>false,'code'=>0,'body'=>null,'raw'=>$response->get_error_message(),'url'=>$url];}
        $code=(int)wp_remote_retrieve_response_code($response);$raw=(string)wp_remote_retrieve_body($response);$safeRaw=$this->redactSecrets($raw,[$apiKey,$identifier]);$decoded=json_decode($safeRaw,true);$bodyDecoded=json_last_error()===JSON_ERROR_NONE?$decoded:$safeRaw;$ok=$code>=200&&$code<300;
        Logger::add($ok?'info':'error',$method,$endpoint.$this->queryString($query),$code,'Legacy API request',$context+['response'=>is_string($bodyDecoded)?mb_substr($bodyDecoded,0,2000):$bodyDecoded]);
        return['ok'=>$ok,'code'=>$code,'body'=>$bodyDecoded,'raw'=>$safeRaw,'url'=>$url];
    }
    private function redactSecrets(string $value,array $secrets):string{foreach($secrets as $secret){$secret=trim((string)$secret);if($secret!=='')$value=str_replace($secret,'***',$value);}return$value;}
    private function queryString(array $query):string{return empty($query)?'':'?'.http_build_query($query);}
}
