<?php
namespace SerbenConnect\Components\Financial;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class NextDueDateComponent extends BaseComponent
{
    protected $shortcode = 'serben_next_due_date';
    protected $title = 'Próximo vencimento';
    protected $category = 'Financeiro';
    protected $description = 'Exibe o próximo vencimento quando a API retornar esse campo.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->financial()->nextDueDate());
    }
}
