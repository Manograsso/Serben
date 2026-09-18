<?php
namespace SerbenConnect\Integrations\Awin;

use SerbenConnect\Support\Logger;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class Client
{
    private $base = 'https://api.awin.com';

    public function get(string $endpoint, array $query = []): array { return $this->request('GET', $endpoint, $query); }
    public function post(string $endpoint, array $body = [], array $query = []): array { return $this->request('POST', $endpoint, $query, $body); }

    private function request(string $method, string $endpoint, array $query = [], array $body = []): array
    {
        $token = trim((string)Settings::get('awin_api_token'));
        $url = $this->base . '/' . ltrim($endpoint, '/');
        if ($query) { $url = add_query_arg($query, $url); }
        if ($token === '') { return ['ok'=>false,'code'=>0,'body'=>null,'raw'=>'Token Awin não configurado.','url'=>$url]; }
        $args = [
            'method' => strtoupper($method), 'timeout' => 30,
            'headers' => ['Authorization'=>'Bearer '.$token,'Accept'=>'application/json','Content-Type'=>'application/json'],
        ];
        if (strtoupper($method) !== 'GET') { $args['body'] = wp_json_encode($body); }
        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            Logger::add('error','AWIN '.$method,$endpoint,0,$response->get_error_message(),['url'=>$url]);
            return ['ok'=>false,'code'=>0,'body'=>null,'raw'=>$response->get_error_message(),'url'=>$url];
        }
        $code=(int)wp_remote_retrieve_response_code($response); $raw=(string)wp_remote_retrieve_body($response);
        $decoded=json_decode($raw,true); $bodyDecoded=json_last_error()===JSON_ERROR_NONE?$decoded:$raw; $ok=$code>=200&&$code<300;
        Logger::add($ok?'info':'error','AWIN '.$method,$endpoint,$code,'Awin API request',['url'=>$url,'response'=>is_string($bodyDecoded)?mb_substr($bodyDecoded,0,1500):$bodyDecoded]);
        return ['ok'=>$ok,'code'=>$code,'body'=>$bodyDecoded,'raw'=>$raw,'url'=>$url];
    }
}
