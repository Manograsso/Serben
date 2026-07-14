<?php
namespace SerbenConnect\Components\Profile;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class EmailComponent extends BaseComponent
{
    protected $shortcode = 'serben_email';
    protected $title = 'E-mail';
    protected $category = 'Perfil';
    protected $description = 'Exibe o e-mail do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->profile()->email());
    }
}
