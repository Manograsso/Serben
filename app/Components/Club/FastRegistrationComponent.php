<?php
namespace SerbenConnect\Components\Club;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class FastRegistrationComponent extends BaseComponent
{
    protected $shortcode = 'serben_fast_registration_enabled';
    protected $title = 'Cadastro ultrarrápido habilitado';
    protected $category = 'Clube';
    protected $description = 'Retorna Sim ou Não para a configuração de cadastro ultrarrápido.';
    public function render(Member $member, array $atts = []): string { return $this->value($member->club()->fastRegistrationEnabled() ? 'Sim' : 'Não'); }
}
