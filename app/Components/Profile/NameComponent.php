<?php
namespace SerbenConnect\Components\Profile;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class NameComponent extends BaseComponent
{
    protected $shortcode = 'serben_name';
    protected $title = 'Nome do associado';
    protected $category = 'Perfil';
    protected $description = 'Exibe o nome completo do associado logado.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->profile()->name());
    }
}
