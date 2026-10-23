<?php
namespace SerbenConnect\Domain;
if (!defined('ABSPATH')) { exit; }
/** C$ Bume / Net Bume is CCI, never Cashback. API values are cents. */
class CashBume {
    private $cliente; private $clubData;
    public function __construct(array $cliente, array $clubData = []) {$this->cliente=$cliente;$this->clubData=$clubData;}
    public function valueInCents() {
        return DataExtractor::first([$this->cliente,$this->clubData], [
            'saldo_cci_liberado','saldo_cci_disponivel','saldo_cci',
            'cci.saldo_liberado','cci.saldo_disponivel','saldos_portador.cci',
            'saldo_cash_bume','saldo_cashbume','saldo_net_bume','saldo_netbume'
        ], null);
    }
    public function formatted(): string {
        $value=$this->valueInCents();
        return is_numeric($value) ? 'C$ '.number_format(((float)$value)/100,2,',','.') : 'Indisponível';
    }
}
