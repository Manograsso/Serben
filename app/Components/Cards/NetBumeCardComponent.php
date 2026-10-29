<?php
namespace SerbenConnect\Components\Cards;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class NetBumeCardComponent extends BaseComponent {
    protected $shortcode = 'serben_card_netbume';
    protected $title = 'Card NET BUME / CASH BUME';
    protected $category = 'Cards';
    protected $description = 'Somente saldo CCI, não substitui por Cashback ou Pontos.';
    public function render(Member $member, array $atts = []): string {
        return $this->card('NET BUME / CASH BUME', $member->cashBume()->formatted(), 'Bonificação de indicações');
    }
}
