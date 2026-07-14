<?php
namespace SerbenConnect\Components\Financial;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PaymentStatusComponent extends BaseComponent
{
    protected $shortcode = 'serben_payment_status';
    protected $title = 'Situação de pagamento';
    protected $category = 'Financeiro';
    protected $description = 'Exibe a situação de pagamento do associado/contrato.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->financial()->paymentStatus());
    }
}
