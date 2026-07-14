<?php
namespace SerbenConnect\Components\Profile;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class CpfComponent extends BaseComponent
{
    protected $shortcode = 'serben_cpf';
    protected $title = 'CPF';
    protected $category = 'Perfil';
    protected $description = 'Exibe o CPF formatado do associado.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->profile()->cpfFormatted());
    }
}
