<?php
namespace SerbenConnect\Core;
use SerbenConnect\Services\PortadoresService;
if (!defined('ABSPATH')) exit;
/** Local institutional affiliations. No external enrollment or status mutation. */
final class EntityMembers {
 const SCHEMA='1.9.0';
 public static function install():void {
  global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
  $t=$wpdb->prefix.'serben_entity_members'; $c=$wpdb->get_charset_collate();
  dbDelta("CREATE TABLE {$t} (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   entity_id bigint(20) unsigned NOT NULL,
   wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
   portador_id bigint(20) unsigned NOT NULL,
   document_hash char(64) NOT NULL,
   document_suffix char(4) NOT NULL,
   member_name varchar(191) NOT NULL,
   status varchar(20) NOT NULL DEFAULT 'active',
   created_by bigint(20) unsigned NOT NULL,
   updated_by bigint(20) unsigned NOT NULL,
   created_at datetime NOT NULL,
   updated_at datetime NOT NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY entity_document (entity_id,document_hash),
   KEY entity_status (entity_id,status),
   KEY wp_user (wp_user_id),
   KEY portador (portador_id)
  ) $c;");
  update_option('serben_entity_members_schema',self::SCHEMA,false);
 }
 public static function register():void {
  add_action('admin_init',[self::class,'upgrade']);
  add_action('admin_post_serben_entity_member_add',[self::class,'add']);
  add_action('admin_post_serben_entity_member_status',[self::class,'changeStatus']);
  add_shortcode('serben_entity_members',[self::class,'render']);
  add_shortcode('serben_entity_member_register',[self::class,'renderRegister']);
  add_shortcode('serben_entity_member_list',[self::class,'renderList']);
  add_action('admin_post_serben_entity_members_export',[self::class,'exportCsv']);
 }
 public static function upgrade():void {if(get_option('serben_entity_members_schema')!==self::SCHEMA && current_user_can('manage_options')) self::install();}
 private static function context():array {
  if(!is_user_logged_in() || !\SerbenConnect\Admin\AccessManagement::accountAllowed(get_current_user_id())) return [0,false];
  $u=wp_get_current_user(); $allowed=['serben_entidade','serben_entidade_diretoria','serben_entidade_secretaria','serben_entidade_atendente'];
  if(!current_user_can('manage_options') && !array_intersect($allowed,(array)$u->roles)) return [0,false];
  $entity=absint(get_user_meta($u->ID,'serben_entity_id',true));
  $write=current_user_can('manage_options') || (bool)array_intersect(['serben_entidade','serben_entidade_diretoria','serben_entidade_secretaria','serben_entidade_atendente'],(array)$u->roles);
  return [$entity,$write];
 }
 private static function go(string $code):void {
  $ref=wp_get_referer() ?: home_url('/');
  wp_safe_redirect(add_query_arg('serben_member_result',$code,remove_query_arg('serben_member_result',$ref)));exit;
 }
 private static function audit(int $id,string $action,array $detail=[]):void {
  global $wpdb;
  $wpdb->insert($wpdb->prefix.'serben_operations_audit',['actor_id'=>get_current_user_id(),'object_type'=>'entity_member','object_id'=>$id,'action'=>$action,'details'=>wp_json_encode($detail),'created_at'=>current_time('mysql')]);
 }
 private static function findWpUser(string $cpf):int {
  foreach(['serben_documento','serben_cpf','cpf'] as $key){
   $users=get_users(['meta_key'=>$key,'meta_value'=>$cpf,'number'=>2,'fields'=>'ID']);
   if(count($users)===1) return (int)$users[0];
  }
  return 0;
 }
 public static function add():void {
  if(!is_user_logged_in()) wp_die('Acesso negado',403);
  check_admin_referer('serben_entity_member_add');
  [$entity,$write]=self::context(); if(!$entity||!$write)wp_die('Entidade não vinculada ou sem permissão',403);
  $cpf=preg_replace('/\D+/','',(string)wp_unslash($_POST['documento']??''));
  if(strlen($cpf)!==11)self::go('invalid_document');
  $response=(new PortadoresService())->getPorDocumento($cpf);
  $data=(new PortadoresService())->extract($response);
  if(!$data || empty($data['id_portador']))self::go('not_found');
  $pid=absint($data['id_portador']);$name=sanitize_text_field((string)($data['nome']??'Associado'));
  global $wpdb;$t=$wpdb->prefix.'serben_entity_members';
  $hash=hash_hmac('sha256',$cpf,wp_salt('auth'));
  $existing=$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE entity_id=%d AND document_hash=%s",$entity,$hash));
  if($existing)self::go('already_linked');
  $uid=self::findWpUser($cpf);$now=current_time('mysql');
  $ok=$wpdb->insert($t,['entity_id'=>$entity,'wp_user_id'=>$uid,'portador_id'=>$pid,'document_hash'=>$hash,'document_suffix'=>substr($cpf,-4),'member_name'=>$name,'status'=>'active','created_by'=>get_current_user_id(),'updated_by'=>get_current_user_id(),'created_at'=>$now,'updated_at'=>$now]);
  if(!$ok)self::go('already_linked');
  self::audit((int)$wpdb->insert_id,'member_linked',['entity_id'=>$entity,'portador_id'=>$pid,'wp_user_id'=>$uid]);
  self::go('linked');
 }
 public static function changeStatus():void {
  check_admin_referer('serben_entity_member_status');
  [$entity,$write]=self::context();if(!$entity||!$write)wp_die('Acesso negado',403);
  $id=absint($_POST['member_id']??0);$status=sanitize_key(wp_unslash($_POST['member_status']??''));
  if(!$id||!in_array($status,['active','paused'],true))self::go('invalid_action');
  global $wpdb;$t=$wpdb->prefix.'serben_entity_members';
  $old=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$t} WHERE id=%d AND entity_id=%d",$id,$entity));
  if($old===null)self::go('invalid_action');
  if($old!==$status){$wpdb->update($t,['status'=>$status,'updated_by'=>get_current_user_id(),'updated_at'=>current_time('mysql')],['id'=>$id,'entity_id'=>$entity]);self::audit($id,'status_changed',['entity_id'=>$entity,'from'=>$old,'to'=>$status]);}
  self::go('updated');
 }
 private static function canExport():bool {
  $u=wp_get_current_user();
  return current_user_can('manage_options') || (bool)array_intersect(['serben_entidade','serben_entidade_diretoria','serben_entidade_secretaria'],(array)$u->roles);
 }
 private static function baseQuery(int $entity,string $search,string $status):array {
  global $wpdb;$t=$wpdb->prefix.'serben_entity_members';
  $where='entity_id=%d';$args=[$entity];
  if($status==='active'||$status==='paused'){$where.=' AND status=%s';$args[]=$status;}
  if($search!==''){
   $where.=' AND (member_name LIKE %s OR document_suffix LIKE %s OR CAST(portador_id AS CHAR) LIKE %s)';
   $like='%'.$wpdb->esc_like($search).'%';array_push($args,$like,$like,$like);
  }
  return [$t,$where,$args];
 }
 public static function renderRegister($atts=[]):string {return self::renderSection(true,false);}
 public static function renderList($atts=[]):string {return self::renderSection(false,true);}
 public static function render($atts=[]):string {return self::renderSection(true,true);}
 public static function exportCsv():void {
  [$entity,$write]=self::context();
  if(!$entity || !$write || !self::canExport())wp_die('Acesso negado',403);
  check_admin_referer('serben_entity_members_export');
  $search=sanitize_text_field(wp_unslash($_GET['member_search']??''));
  $status=sanitize_key(wp_unslash($_GET['member_filter']??''));
  [$t,$where,$args]=self::baseQuery($entity,$search,$status);
  global $wpdb;
  $rows=$wpdb->get_results($wpdb->prepare("SELECT member_name,document_suffix,portador_id,wp_user_id,status,created_at FROM {$t} WHERE {$where} ORDER BY id DESC LIMIT 10000",$args),ARRAY_A);
  nocache_headers();header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="serben-associados-entidade-'.$entity.'.csv"');
  $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
  fputcsv($out,['Nome','CPF final','ID Portador','ID Usuario WP','Situacao local','Vinculado em'],';');
  foreach($rows as $r){
   $cells=[$r['member_name'],'***'.$r['document_suffix'],$r['portador_id'],$r['wp_user_id'],$r['status'],$r['created_at']];
   $cells=array_map(static function($v){$v=(string)$v;return preg_match('/^[\s]*[=+@\-]/u',$v)?"'".$v:$v;},$cells);
   fputcsv($out,$cells,';');
  }
  fclose($out);exit;
 }
 private static function renderSection(bool $showForm,bool $showList):string {
  [$entity,$write]=self::context();if(!$entity)return '<p>Esta conta precisa estar vinculada a uma entidade para gerenciar associados.</p>';
  $messages=['linked'=>'Associado existente no App vinculado à entidade.','already_linked'=>'Este associado já está vinculado à entidade.','not_found'=>'Portador não encontrado na API. Nenhum cadastro foi criado.','invalid_document'=>'Documento inválido.','updated'=>'Situação local atualizada.','invalid_action'=>'Operação inválida.'];
  $out='<div class="serben-entity-members"><h3>Associados da entidade</h3>';
  $result=sanitize_key(wp_unslash($_GET['serben_member_result']??''));if(isset($messages[$result]))$out.='<p role="status">'.esc_html($messages[$result]).'</p>';
  if($showForm){
   $out.='<p>Vinculação a cadastro existente no App SerBen. Não cria outra conta, não modifica o cadastro externo e não movimenta saldos.</p>';
   if($write)$out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="serben_entity_member_add">'.wp_nonce_field('serben_entity_member_add','_wpnonce',true,false).'<label>CPF do associado já cadastrado no App <input name="documento" inputmode="numeric" autocomplete="off" required maxlength="14"></label> <button type="submit">Consultar e vincular</button></form>';
  }
  if($showList){
   global $wpdb;
   $search=sanitize_text_field(wp_unslash($_GET['member_search']??''));
   $status=sanitize_key(wp_unslash($_GET['member_filter']??''));
   if(!in_array($status,['active','paused'],true))$status='';
   $page=max(1,absint($_GET['member_page']??1));$limit=25;$offset=($page-1)*$limit;
   [$t,$where,$args]=self::baseQuery($entity,$search,$status);
   $total=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE {$where}",$args));
   $rows=$wpdb->get_results($wpdb->prepare("SELECT id,member_name,document_suffix,portador_id,wp_user_id,status,created_at FROM {$t} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d",array_merge($args,[$limit,$offset])),ARRAY_A);
   $out.='<form method="get"><label>Buscar nome, final do CPF ou ID do portador <input name="member_search" value="'.esc_attr($search).'"></label> <label>Situação <select name="member_filter"><option value="">Todas</option><option value="active"'.selected($status,'active',false).'>Ativos</option><option value="paused"'.selected($status,'paused',false).'>Pausados</option></select></label> <button type="submit">Filtrar</button></form>';
   if(self::canExport()){
    $url=wp_nonce_url(add_query_arg(['action'=>'serben_entity_members_export','member_search'=>$search,'member_filter'=>$status],admin_url('admin-post.php')),'serben_entity_members_export');
    $out.='<p><a href="'.esc_url($url).'">Exportar resultado em CSV</a></p>';
   }
   $out.='<p>'.esc_html((string)$total).' vínculo(s) encontrado(s).</p><table class="serben-dashboard-table"><thead><tr><th>Nome</th><th>CPF final</th><th>Portador</th><th>Conta WP</th><th>Situação local</th><th>Ação</th></tr></thead><tbody>';
   foreach($rows as $r){$out.='<tr><td>'.esc_html($r['member_name']).'</td><td>***'.esc_html($r['document_suffix']).'</td><td>'.esc_html($r['portador_id']).'</td><td>'.($r['wp_user_id']?'Vinculada':'Não localizada').'</td><td>'.esc_html($r['status']==='active'?'Ativo':'Pausado').'</td><td>';
    if($write){$next=$r['status']==='active'?'paused':'active';$out.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="serben_entity_member_status"><input type="hidden" name="member_id" value="'.absint($r['id']).'"><input type="hidden" name="member_status" value="'.esc_attr($next).'">'.wp_nonce_field('serben_entity_member_status','_wpnonce',true,false).'<button type="submit">'.($next==='paused'?'Pausar':'Reativar').'</button></form>';}
    $out.='</td></tr>';
   }
   if(!$rows)$out.='<tr><td colspan="6">Nenhum associado encontrado.</td></tr>';
   $out.='</tbody></table>';
   $pages=(int)ceil($total/$limit);
   if($pages>1){$out.='<nav aria-label="Páginas de associados">';for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++){$url=add_query_arg(['member_page'=>$i,'member_search'=>$search,'member_filter'=>$status]);$out.=' <a href="'.esc_url($url).'"'.($i===$page?' aria-current="page"':'').'>'.esc_html((string)$i).'</a> ';}$out.='</nav>';}
   $out.='<p><small>Pausar/reativar afeta somente o vínculo local, não o status do portador no App.</small></p>';
  }
  return $out.'</div>';
 }
}
