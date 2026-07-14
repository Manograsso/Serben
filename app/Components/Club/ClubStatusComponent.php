<?php
namespace SerbenConnect\Components\Club;

use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;

if (!defined('ABSPATH')) { exit; }

class ClubStatusComponent extends BaseComponent
{
    protected $shortcode = 'serben_club_status';
    protected $title = 'Status do vínculo com o clube';
    protected $category = 'Clube';
    protected $description = 'Informa se o associado possui vínculo ativo com a unidade configurada.';

    public function render(Member $member, array $atts = []): string
    {
        return $this->value($member->club()->message());
    }
}
