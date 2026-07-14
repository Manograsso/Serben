<?php
namespace SerbenConnect\Components\Profile;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class FirstNameComponent extends BaseComponent
{
    protected $shortcode = 'serben_first_name';
    protected $title = 'Primeiro nome';
    protected $category = 'Perfil';
    protected $description = 'Exibe apenas o primeiro nome do associado logado.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->profile()->firstName());
    }
}
