<?php
namespace SerbenConnect\Components\Club;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class UsesCashbackComponent extends BaseComponent
{
    protected $shortcode = 'serben_uses_cashback';
    protected $title = 'Loja utiliza cashback/débito';
    protected $category = 'Clube';
    protected $description = 'Retorna Sim ou Não conforme a configuração de débito/cashback.';
    public function render(Member $member, array $atts = []): string { return $this->value($member->club()->usesDebit() ? 'Sim' : 'Não'); }
}
