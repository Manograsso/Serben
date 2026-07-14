<?php
namespace SerbenConnect\Components\Club;
use SerbenConnect\Components\BaseComponent;
use SerbenConnect\Domain\Member;
if (!defined('ABSPATH')) { exit; }
class StoreIdComponent extends BaseComponent
{
    protected $shortcode = 'serben_store_id';
    protected $title = 'ID da loja';
    protected $category = 'Clube';
    protected $description = 'Exibe o ID da unidade vinculada.';
    public function render(Member $member, array $atts = []): string { return $this->value($member->club()->storeId()); }
}
