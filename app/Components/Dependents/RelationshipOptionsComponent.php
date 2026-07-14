<?php
namespace SerbenConnect\Components\Dependents;
use SerbenConnect\Components\BaseComponent; use SerbenConnect\Domain\Member; use SerbenConnect\Services\DependentesService;
if (!defined('ABSPATH')) { exit; }
class RelationshipOptionsComponent extends BaseComponent {
 protected $shortcode='serben_relationship_options'; protected $title='Opções de parentesco'; protected $category='Dependentes'; protected $description='Renderiza um select com os graus de parentesco disponíveis na API.';
 public function render(Member $member,array $atts=[]): string {
  $atts=shortcode_atts(['field_id'=>'serben_dep_parentesco','name'=>'serben_dep_parentesco','placeholder'=>'Selecione o parentesco'],$atts,$this->shortcode);
  $r=(new DependentesService())->parentescos(); $body=$r['body']??[]; $items=$this->normalize($body);
  $html='<select id="'.esc_attr($atts['field_id']).'" name="'.esc_attr($atts['name']).'"><option value="">'.esc_html($atts['placeholder']).'</option>';
  foreach($items as $i){$id=$i['id']??$i['id_parentesco']??$i['codigo']??'';$label=$i['descricao']??$i['parentesco']??$i['nome']??'';if($id!==''&&$label!=='')$html.='<option value="'.esc_attr((string)$id).'">'.esc_html((string)$label).'</option>';}
  return $html.'</select>';
 }
 private function normalize($b): array { if(!is_array($b))return []; foreach(['data','registros','items','parentescos'] as $k){if(isset($b[$k]))return $this->normalize($b[$k]);} if(array_is_list($b))return array_values(array_filter($b,'is_array')); foreach($b as $v){if(is_array($v)&&array_is_list($v))return array_values(array_filter($v,'is_array'));} return []; }
}
