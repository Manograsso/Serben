<?php
namespace SerbenConnect\Components\Club;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class UsesPointsComponent extends BaseComponent
{
    protected $shortcode = 'serben_uses_points';
    protected $title = 'Loja utiliza pontos';
    protected $category = 'Clube';
    protected $description = 'Retorna Sim ou Não conforme a configuração de pontos.';
    public function render(Member $member, array $atts = []): string { return $this->value($member->club()->usesPoints() ? 'Sim' : 'Não'); }
}
