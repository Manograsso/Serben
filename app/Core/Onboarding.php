<?php
namespace SerbenConnect\Core;
if (!defined('ABSPATH')) exit;
/** Public applications are reviewed before any account or partner is activated. */
final class Onboarding {
 public static function register(): void {
  add_shortcode('serben_partner_apply',[self::class,'partner']);
  add_shortcode('serben_entity_apply',[self::class,'entity']);
  add_action('admin_post_nopriv_serben_apply',[self::class,'submit']);
  add_action('admin_post_serben_apply',[self::class,'submit']);
  add_action('admin_post_serben_review_application',[self::class,'review']);
  add_action('admin_menu',[self::class,'menu']);
  add_action('init',[self::class,'cpt']);
 }
 public static function cpt(): void {register_post_type('serben_application',['labels'=>['name'=>'Solicitações SerBen','singular_name'=>'Solicitação SerBen'],'public'=>false,'show_ui'=>false,'supports'=>['title']]);}
 public static function partner(): string {return self::form('partner');}
 public static function entity(): string {return self::form('entity');}
 private static function form(string $type): string {
  $labels=$type==='partner'?['Parceiro / Lojista','Nome fantasia / Loja']:['Entidade / Sindicato','Nome fantasia / Entidade'];
  $out='<section class="serben-onboarding"><h2>Credenciamento — '.esc_html($labels[0]).'</h2><p>Envie seus dados para análise da SerBen. O envio não ativa automaticamente o cadastro no aplicativo.</p>';
  if(isset($_GET['serben_apply'])){$status=sanitize_key(wp_unslash($_GET['serben_apply']));if($status==='ok')$out.='<p role="status">Solicitação recebida para análise.</p>';elseif($status==='error')$out.='<p role="alert">Não foi possível registrar. Confira os dados e tente novamente.</p>';}
  $out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">'.wp_nonce_field('serben_apply_'.$type,'serben_nonce',true,false).'<input type="hidden" name="action" value="serben_apply"><input type="hidden" name="kind" value="'.esc_attr($type).'">';
  foreach(['legal_name'=>'Razão social','trade_name'=>$labels[1],'cnpj'=>'CNPJ','representative'=>'Responsável / Representante','email'=>'E-mail','phone'=>'Telefone / WhatsApp','address'=>'Endereço completo'] as $key=>$label){$input_type=$key==='email'?'email':'text';$out.='<p><label>'.esc_html($label).' <input required maxlength="190" type="'.$input_type.'" name="'.esc_attr($key).'" autocomplete="off"></label></p>';}
  $out.='<p><label><input type="checkbox" required name="consent" value="1"> Autorizo o tratamento dos dados informados para análise do credenciamento.</label></p><button type="submit">Enviar solicitação</button></form></section>';return $out;
 }
 public static function submit(): void {
  $kind=isset($_POST['kind'])?sanitize_key(wp_unslash($_POST['kind'])):'';
  if(!in_array($kind,['partner','entity'],true)||!isset($_POST['serben_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_nonce'])),'serben_apply_'.$kind))wp_die('Solicitação inválida.',403);
  $ref=wp_get_referer()?:home_url('/');$fail=static function()use($ref){wp_safe_redirect(add_query_arg('serben_apply','error',$ref));exit;};
  if(empty($_POST['consent']))$fail();
  $data=[];foreach(['legal_name','trade_name','cnpj','representative','email','phone','address'] as $field){$raw=isset($_POST[$field])?wp_unslash($_POST[$field]):'';$data[$field]=$field==='email'?sanitize_email($raw):sanitize_text_field($raw);if(!$data[$field]||mb_strlen($data[$field])>190)$fail();}
  $cnpj=preg_replace('/\D/','',$data['cnpj']);if(strlen($cnpj)!==14||!is_email($data['email']))$fail();
  $recent=get_transient('serben_apply_'.hash('sha256',$kind.$cnpj));if($recent)$fail();
  $post_id=wp_insert_post(['post_type'=>'serben_application','post_status'=>'private','post_title'=>($kind==='partner'?'Parceiro: ':'Entidade: ').$data['trade_name'],'post_author'=>get_current_user_id()],true);
  if(is_wp_error($post_id))$fail();
  update_post_meta($post_id,'serben_apply_kind',$kind);update_post_meta($post_id,'serben_apply_status','pending');
  foreach($data as $key=>$value)update_post_meta($post_id,'serben_apply_'.$key,$value);
  set_transient('serben_apply_'.hash('sha256',$kind.$cnpj),1,10*MINUTE_IN_SECONDS);
  wp_mail(get_option('admin_email'),'Nova solicitação SerBen #'.$post_id,'Nova solicitação de '.($kind==='partner'?'parceiro':'entidade').'. Analise em Configurações > Credenciamentos SerBen.');
  wp_safe_redirect(add_query_arg('serben_apply','ok',$ref));exit;
 }
 public static function menu(): void {add_submenu_page('options-general.php','Credenciamentos SerBen','Credenciamentos SerBen','manage_options','serben-applications',[self::class,'admin']);}
 public static function admin(): void {
  if(!current_user_can('manage_options'))wp_die('Acesso negado');
  $items=get_posts(['post_type'=>'serben_application','post_status'=>'private','numberposts'=>100,'orderby'=>'date','order'=>'DESC']);
  echo '<div class="wrap"><h1>Credenciamentos SerBen</h1><p>As solicitações são locais. Aprovação não cria registros no App e não realiza movimentações financeiras.</p><table class="widefat striped"><thead><tr><th>ID</th><th>Tipo</th><th>Razão social / Nome</th><th>CNPJ</th><th>Contato</th><th>Status</th><th>Ações</th></tr></thead><tbody>';
  foreach($items as $p){$id=$p->ID;$meta=static function($k)use($id){return get_post_meta($id,'serben_apply_'.$k,true);};$status=$meta('status');echo '<tr><td>'.esc_html($id).'</td><td>'.esc_html($meta('kind')).'</td><td>'.esc_html($meta('legal_name')).'<br>'.esc_html($meta('trade_name')).'</td><td>'.esc_html($meta('cnpj')).'</td><td>'.esc_html($meta('representative')).'<br>'.esc_html($meta('email')).'<br>'.esc_html($meta('phone')).'<br>'.esc_html($meta('address')).'</td><td>'.esc_html($status).'</td><td>';
   foreach(['approved'=>'Aprovar análise','rejected'=>'Rejeitar'] as $value=>$label){$url=wp_nonce_url(add_query_arg(['action'=>'serben_review_application','id'=>$id,'decision'=>$value],admin_url('admin-post.php')),'serben_review_'.$id);echo '<a class="button" href="'.esc_url($url).'">'.esc_html($label).'</a> ';}echo '</td></tr>';}
  echo '</tbody></table></div>';
 }
 public static function review(): void {
  if(!current_user_can('manage_options'))wp_die('Acesso negado');$id=isset($_GET['id'])?absint($_GET['id']):0;check_admin_referer('serben_review_'.$id);
  $decision=isset($_GET['decision'])?sanitize_key(wp_unslash($_GET['decision'])):'';
  if(get_post_type($id)!=='serben_application'||!in_array($decision,['approved','rejected'],true))wp_die('Solicitação inválida.');
  update_post_meta($id,'serben_apply_status',$decision);update_post_meta($id,'serben_apply_reviewed_by',get_current_user_id());update_post_meta($id,'serben_apply_reviewed_at',current_time('mysql'));
  wp_safe_redirect(admin_url('options-general.php?page=serben-applications'));exit;
 }
}
