<?php
namespace SerbenConnect\Components\Profile;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class PhotoComponent extends BaseComponent
{
    protected $shortcode = 'serben_photo';
    protected $title = 'Foto do associado';
    protected $category = 'Perfil';
    protected $description = 'Exibe a foto retornada pela API quando disponível.';
    public function render(Member $member, array $atts = []): string
    {
        $url = $member->card()->photoUrl();
        if ($url === '') { return ''; }
        $alt = $member->profile()->name();
        return '<img class="serben-member-photo" src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '">';
    }
}
