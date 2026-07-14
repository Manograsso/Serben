<?php
namespace SerbenConnect\Components\Dependents;
use SerbenConnect\Components\BaseComponent; use SerbenConnect\Domain\Member; use SerbenConnect\Providers\DependentsProvider;
if (!defined('ABSPATH')) { exit; }
class DependentsCountComponent extends BaseComponent {
 protected $shortcode='serben_dependents_count'; protected $title='Quantidade de dependentes'; protected $category='Dependentes'; protected $description='Retorna a quantidade de dependentes do associado.';
 public function render(Member $member,array $atts=[]): string { $d=(new DependentsProvider())->get($member->profile()->cpf()); return esc_html((string)($d['count']??0)); }
}
