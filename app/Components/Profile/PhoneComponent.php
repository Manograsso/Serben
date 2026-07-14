<?php
namespace SerbenConnect\Components\Profile;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class PhoneComponent extends BaseComponent
{
    protected $shortcode = 'serben_phone';
    protected $title = 'Telefone/celular';
    protected $category = 'Perfil';
    protected $description = 'Exibe o telefone ou celular do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->profile()->phone());
    }
}
