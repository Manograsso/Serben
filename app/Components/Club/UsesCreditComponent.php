<?php
namespace SerbenConnect\Components\Club;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class UsesCreditComponent extends BaseComponent
{
    protected $shortcode = 'serben_uses_credit';
    protected $title = 'Loja utiliza crédito';
    protected $category = 'Clube';
    protected $description = 'Retorna Sim ou Não conforme a configuração de crédito.';
    public function render(Member $member, array $atts = []): string { return $this->value($member->club()->usesCredit() ? 'Sim' : 'Não'); }
}
