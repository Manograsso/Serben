<?php
namespace SerbenConnect\Services;
use SerbenConnect\API\Client; use SerbenConnect\Support\Settings;
if(!defined('ABSPATH')){exit;}
final class FidelidadeService
{
    private $client; public function __construct(?Client $client=null){$this->client=$client?:new Client();}
    public function registrarCashbackExterno(string $cpf,int $saleCents,int $cashbackCents): array
    {
        return $this->client->post('transacoes/fidelidade/valor_cashback',[
            'idLoja'=>(int)Settings::get('awin_serben_store_id',1), 'cpf'=>preg_replace('/\D+/','',$cpf), 'idUsuario'=>(int)Settings::get('awin_serben_user_id',1), 'valor'=>$saleCents, 'valor_cashback'=>$cashbackCents,
        ],true);
    }
}
