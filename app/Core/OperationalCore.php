<?php
namespace SerbenConnect\Core;
if (!defined('ABSPATH')) { exit; }
/** Operational accounting foundation. No automatic external credits or payouts. */
final class OperationalCore {
 const SCHEMA = '1.7.0';
 public static function install(): void {
  global $wpdb;
  require_once ABSPATH.'wp-admin/includes/upgrade.php';
  $collate=$wpdb->get_charset_collate(); $p=$wpdb->prefix.'serben_';
  dbDelta("CREATE TABLE {$p}ledger (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   account_type varchar(24) NOT NULL,
   account_id bigint(20) unsigned NOT NULL,
   currency varchar(24) NOT NULL,
   amount decimal(18,4) NOT NULL,
   source varchar(64) NOT NULL,
   external_ref varchar(191) NOT NULL,
   status varchar(20) NOT NULL DEFAULT 'pending',
   effective_at datetime NOT NULL,
   created_at datetime NOT NULL,
   actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
   PRIMARY KEY  (id),
   UNIQUE KEY source_reference (source,external_ref,account_type,account_id,currency),
   KEY account_status (account_type,account_id,currency,status)
  ) $collate;");
  dbDelta("CREATE TABLE {$p}entity_periods (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   entity_id bigint(20) unsigned NOT NULL,
   period char(7) NOT NULL,
   cashback decimal(18,4) NOT NULL DEFAULT 0,
   cci decimal(18,4) NOT NULL DEFAULT 0,
   points decimal(18,4) NOT NULL DEFAULT 0,
   commission_rate decimal(8,4) NOT NULL DEFAULT 0,
   payable decimal(18,4) NOT NULL DEFAULT 0,
   status varchar(24) NOT NULL DEFAULT 'draft',
   approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
   approved_at datetime DEFAULT NULL,
   PRIMARY KEY  (id),
   UNIQUE KEY entity_period (entity_id,period)
  ) $collate;");
  dbDelta("CREATE TABLE {$p}operations_audit (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   actor_id bigint(20) unsigned NOT NULL,
   object_type varchar(40) NOT NULL,
   object_id bigint(20) unsigned NOT NULL DEFAULT 0,
   action varchar(64) NOT NULL,
   details longtext NULL,
   created_at datetime NOT NULL,
   PRIMARY KEY  (id),
   KEY object_lookup (object_type,object_id)
  ) $collate;");
  update_option('serben_operational_schema',self::SCHEMA,false);
 }
 public static function register(): void {
  add_action('admin_init',[self::class,'upgrade']);
  add_action('admin_menu',[self::class,'menu']);
  add_action('admin_post_serben_operational_save',[self::class,'save']);
  add_shortcode('serben_entity_operational_dashboard',[self::class,'entityDashboard']);
  add_shortcode('serben_partner_operational_dashboard',[self::class,'partnerDashboard']);
  foreach(['perfil','associados','conta','faturas','midia'] as $section) {
   add_shortcode('serben_entity_'.$section.'_module',static function() use ($section) { return self::entityDashboard($section,true); });
  }
  foreach(['perfil','conta','marketing','validador','contrato'] as $section) {
   add_shortcode('serben_partner_'.$section.'_module',static function() use ($section) { return self::partnerModule($section); });
  }
  add_action('show_user_profile',[self::class,'identityFields']);
  add_action('edit_user_profile',[self::class,'identityFields']);
  add_action('personal_options_update',[self::class,'saveIdentityFields']);
  add_action('edit_user_profile_update',[self::class,'saveIdentityFields']);
 }
 public static function upgrade(): void { if (get_option('serben_operational_schema')!==self::SCHEMA && current_user_can('manage_options')) self::install(); }
 public static function menu(): void { add_submenu_page('options-general.php','Operação SerBen','Operação SerBen','manage_options','serben-operational',[self::class,'settings']); }
 public static function settings(): void {
  if(!current_user_can('manage_options')) wp_die('Acesso negado');
  $rate=get_option('serben_operational_default_entity_rate','10');
  echo '<div class="wrap"><h1>SerBen — Núcleo Operacional</h1><p>Parâmetros internos. Nenhuma operação de pagamento, saque ou crédito externo é executada por esta tela.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
  wp_nonce_field('serben_operational_save');
  echo '<input type="hidden" name="action" value="serben_operational_save"><table class="form-table"><tr><th>Comissão padrão de entidades (%)</th><td><input type="number" min="0" max="100" step="0.01" name="rate" value="'.esc_attr($rate).'"> <p class="description">A taxa contratual específica de cada entidade prevalece sobre a padrão.</p></td></tr></table><button class="button button-primary">Salvar</button></form><p>Modelo de dados: lançamentos auditáveis, fechamento mensal de entidades e trilha de auditoria. Valores somente liberados após homologação em futura etapa.</p></div>';
 }
 public static function save(): void {
  if(!current_user_can('manage_options')) wp_die('Acesso negado');
  check_admin_referer('serben_operational_save');
  $rate=isset($_POST['rate'])?(float)wp_unslash($_POST['rate']):10;
  update_option('serben_operational_default_entity_rate',(string)max(0,min(100,$rate)));
  wp_safe_redirect(admin_url('options-general.php?page=serben-operational&updated=1')); exit;
 }
 private static function guard(array $roles): bool { $u=wp_get_current_user(); return is_user_logged_in() && (current_user_can('manage_options') || (bool)array_intersect($roles,(array)$u->roles)); }
 public static function entityDashboard(?string $module=null, bool $standalone=false): string {
  if(!self::guard(['serben_entidade','serben_entidade_diretoria','serben_entidade_secretaria','serben_entidade_atendente'])) return '<p>Entre com uma conta de entidade autorizada.</p>';
  global $wpdb;
  $uid=get_current_user_id(); $user=wp_get_current_user(); $roles=(array)$user->roles;
  $director=current_user_can('manage_options') || (bool)array_intersect(['serben_entidade','serben_entidade_diretoria'],$roles);
  $secretary=$director || in_array('serben_entidade_secretaria',$roles,true);
  $entity_id=absint(get_user_meta($uid,'serben_entity_id',true));
  // Financial records must never be associated with a WordPress user ID by fallback.
  // The entity identifier must be explicitly provisioned by SerBen administration.
  $entity_linked=$entity_id>0;
  $entity_name=(string)(get_user_meta($uid,'serben_entity_name',true) ?: $user->display_name);
  $tab=$module ?: (isset($_GET['serben_tab'])?sanitize_key(wp_unslash($_GET['serben_tab'])):'perfil');
  $tabs=['perfil'=>'Perfil da Entidade','associados'=>'Associados & Planos','conta'=>'Minha Conta','faturas'=>'Minhas Faturas','midia'=>'Peças Publicitárias & Mídia'];
  if(!isset($tabs[$tab]))$tab='perfil';
  if(!$secretary && in_array($tab,['conta','faturas','midia'],true))$tab='associados';
  if(!$director && in_array($tab,['conta','faturas'],true))$tab='associados';
  $url=remove_query_arg('serben_tab');
  $out='<section class="serben-operational serben-entity-portal">';
  if(!$standalone)$out.='<header><h2>Portal da Entidade</h2><p>'.esc_html($entity_name).'</p></header><nav class="serben-operational-nav" aria-label="Menu da entidade">';
  if(!$standalone)foreach($tabs as $key=>$label){if((!$secretary&&in_array($key,['conta','faturas','midia'],true))||(!$director&&in_array($key,['conta','faturas'],true)))continue;$out.='<a class="'.($tab===$key?'active':'').'" href="'.esc_url(add_query_arg('serben_tab',$key,$url)).'">'.esc_html($label).'</a>';}
  if(!$standalone)$out.='</nav>';
  $out.='<div class="serben-operational-content"><h3>'.esc_html($tabs[$tab]).'</h3>'; 
  $field=static function($key,$fallback='—')use($uid){$v=get_user_meta($uid,$key,true);return esc_html($v!==''?(string)$v:$fallback);};
  if($tab==='perfil'){
   $logo=esc_url((string)get_user_meta($uid,'serben_entity_logo_url',true));
   if($logo)$out.='<img class="serben-entity-logo" src="'.$logo.'" alt="Logotipo da entidade">';
   $out.='<dl class="serben-operational-details"><dt>Razão social</dt><dd>'.$field('serben_entity_name',$entity_name).'</dd><dt>Nome fantasia</dt><dd>'.$field('serben_entity_trade_name').'</dd><dt>CNPJ</dt><dd>'.$field('serben_entity_cnpj').'</dd><dt>Representante legal</dt><dd>'.$field('serben_entity_representative').'</dd><dt>Endereço</dt><dd>'.$field('serben_entity_address').'</dd><dt>Telefone / WhatsApp</dt><dd>'.$field('serben_entity_phone').'</dd><dt>E-mail oficial</dt><dd>'.$field('serben_entity_email').'</dd></dl>';
  }elseif($tab==='associados'){
   $count=(int)get_user_meta($uid,'serben_entity_lives',true);
   $out.='<div class="serben-component-grid"><div class="serben-dashboard-readonly"><h4>Plano institucional</h4><strong>'.$field('serben_entity_plan','Não informado').'</strong></div><div class="serben-dashboard-readonly"><h4>Plano do associado</h4><strong>'.$field('serben_entity_member_plan','Não informado').'</strong></div><div class="serben-dashboard-readonly"><h4>Vidas declaradas</h4><strong>'.number_format_i18n($count).'</strong></div><div class="serben-dashboard-readonly"><h4>Conveniado desde</h4><strong>'.$field('serben_entity_since','Não informado').'</strong></div></div>';
   $out.=EntityMembers::render();
   $out.='<p><strong>Solicitação de benefícios:</strong> entre em contato com a administração SerBen para solicitar aditivos contratuais e upgrades.</p>';
  }elseif($tab==='conta'){
   $table=$wpdb->prefix.'serben_entity_periods';
   $rows=$entity_linked ? $wpdb->get_results($wpdb->prepare("SELECT period,cashback,cci,points,commission_rate,payable,status FROM {$table} WHERE entity_id=%d ORDER BY period DESC LIMIT 12",$entity_id),ARRAY_A) : [];
   if(!$entity_linked)$out.='<p><strong>Vínculo institucional pendente:</strong> solicite à administração SerBen a configuração do identificador da entidade. Nenhuma informação financeira será exibida até a validação.</p>';
   $latest=$rows[0]??null;
   $money=static function($n){return 'R$ '.number_format_i18n((float)$n,2);};
   $out.='<p>Os valores abaixo são apurações internas registradas pela administração, não saldos em tempo real da API.</p>';
   if($latest){$out.='<p><strong>Competência:</strong> '.esc_html($latest['period']).' — <strong>Situação:</strong> '.esc_html($latest['status']==='approved'?'Homologado':'Aguardando homologação').'</p><div class="serben-component-grid">';
    foreach(['cashback'=>'Cashback da base','cci'=>'C$ Bume (CCI) da base','payable'=>'Remuneração da entidade'] as $key=>$label){
     $display=($key==='payable' && $latest['status']!=='approved') ? 'Em conferência' : $money($latest[$key]);
     $out.='<div class="serben-dashboard-readonly"><h4>'.esc_html($label).'</h4><strong>'.esc_html($display).'</strong></div>';
    }
    $out.='<div class="serben-dashboard-readonly"><h4>Bumes / Pontos (informativo)</h4><strong>'.number_format_i18n((float)$latest['points'],2).'</strong></div></div>';
    if($latest['status']!=='approved')$out.='<p><strong>Saldo indisponível:</strong> esta competência ainda não foi homologada.</p>';
    else $out.='<p>Apuração homologada. Transferências e conversões não estão habilitadas nesta etapa; exigem conferência de titularidade e integração financeira.</p>';
   }else $out.='<p>Nenhuma competência importada ou homologada. Não há saldo disponível para resgate.</p>';
   $out.='<h4>Histórico de competências</h4><table class="serben-dashboard-table"><thead><tr><th>Período</th><th>Cashback</th><th>CCI</th><th>Remuneração</th><th>Status</th></tr></thead><tbody>';
   foreach($rows as $r)$out.='<tr><td>'.esc_html($r['period']).'</td><td>'.$money($r['cashback']).'</td><td>'.$money($r['cci']).'</td><td>'.$money($r['payable']).'</td><td>'.esc_html($r['status']).'</td></tr>';
   if(!$rows)$out.='<tr><td colspan="5">Sem competências registradas.</td></tr>';
   $out.='</tbody></table><p><small>Fórmula: (Cashback + CCI) × alíquota contratual. Pontos não compõem a remuneração. Transferências somente para CNPJ da entidade, após conferência.</small></p>';
  }elseif($tab==='faturas'){
   $out.='<p>Faturas e documentos financeiros ainda não sincronizados. A emissão de boletos/Pix será habilitada somente após configuração do gateway e validação dos documentos fiscais.</p>';
  }else{
   $out.='<p>A galeria institucional será disponibilizada quando a administração publicar os criativos e campanhas oficiais.</p>';
  }
  return $out.'</div></section>';
 }
 public static function partnerDashboard(): string {
  if(!self::guard(['serben_lojista'])) return '<p>Entre com uma conta de lojista autorizada.</p>';
  $items=['Perfil & Filiais','Minha Conta','Marketing / Criativos & Promoções','Validador de Balcão','Termo de Adesão & Contrato'];
  $out='<section class="serben-operational"><h2>Portal do Credenciado</h2><p>Estrutura operacional em implantação. Validações, créditos e recargas dependem de integração e não estão habilitados.</p><div class="serben-component-grid">';
  foreach($items as $item) $out.='<div class="serben-dashboard-readonly"><h3>'.esc_html($item).'</h3><p>Em implantação</p></div>';
  return $out.'</div></section>';
 }
 public static function partnerModule(string $section): string {
  if(!self::guard(['serben_lojista'])) return '<p>Entre com uma conta de lojista autorizada.</p>';
  $uid=get_current_user_id(); $post_id=absint(get_user_meta($uid,'serben_partner_post_id',true));
  if(!$post_id || get_post_type($post_id)!=='serben_parceiro')return '<p>Conta sem parceiro válido vinculado. Solicite ao administrador o vínculo com o cadastro da loja.</p>';
  $modules=['perfil'=>'partnerGovernance','conta'=>'partnerWallet','marketing'=>'partnerCoupons'];
  if(isset($modules[$section])){
   $class=new \SerbenConnect\Shortcodes\DashboardModulesShortcodes();
   return $class->{$modules[$section]}();
  }
  if($section==='validador')return '<section class="serben-operational"><h3>Validador de balcão</h3><p>A consulta de portadores e a baixa de cupons exigem autorização de operação e integração validada. Nenhuma baixa é executada nesta tela.</p></section>';
  $contract=esc_url((string)get_post_meta($post_id,'serben_partner_contract_url',true));
  return '<section class="serben-operational"><h3>Termo de adesão</h3>'.($contract?'<p><a href="'.$contract.'" target="_blank" rel="noopener">Consultar contrato PDF</a></p>':'<p>Contrato ainda não disponibilizado pela administração.</p>').'</section>';
 }
 public static function identityFields($user): void {
  if(!current_user_can('edit_users'))return;
  echo '<h2>SerBen — Vínculos de acesso</h2><table class="form-table">';
  foreach(['serben_entity_id'=>'ID institucional da entidade (apuração interna)','serben_partner_post_id'=>'ID do post serben_parceiro (lojista)','serben_entity_name'=>'Razão social / nome da entidade','serben_entity_cnpj'=>'CNPJ da entidade','serben_entity_plan'=>'Plano institucional','serben_entity_trade_name'=>'Nome fantasia','serben_entity_representative'=>'Representante legal','serben_entity_phone'=>'Telefone','serben_entity_email'=>'E-mail oficial','serben_entity_address'=>'Endereço','serben_entity_logo_url'=>'URL do logotipo','serben_entity_member_plan'=>'Plano dos associados','serben_entity_lives'=>'Vidas declaradas','serben_entity_since'=>'Conveniado desde'] as $key=>$label){
   echo '<tr><th><label for="'.esc_attr($key).'">'.esc_html($label).'</label></th><td><input class="regular-text" id="'.esc_attr($key).'" name="'.esc_attr($key).'" value="'.esc_attr((string)get_user_meta($user->ID,$key,true)).'"></td></tr>';
  }
  wp_nonce_field('serben_identity_link_'.$user->ID,'serben_identity_nonce');
  echo '</table><p>O ID da entidade é interno e deve corresponder à competência da entidade. Não utilize o ID do usuário como substituto.</p>';
 }
 public static function saveIdentityFields(int $user_id): void {
  if(!current_user_can('edit_user',$user_id)||!current_user_can('edit_users'))return;
  if(!isset($_POST['serben_identity_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_identity_nonce'])),'serben_identity_link_'.$user_id))return;
  foreach(['serben_entity_id','serben_partner_post_id','serben_entity_name','serben_entity_cnpj','serben_entity_plan','serben_entity_trade_name','serben_entity_representative','serben_entity_phone','serben_entity_email','serben_entity_address','serben_entity_logo_url','serben_entity_member_plan','serben_entity_lives','serben_entity_since'] as $key){
   if(!isset($_POST[$key]))continue;
   $value=wp_unslash($_POST[$key]);
   if(in_array($key,['serben_entity_id','serben_partner_post_id','serben_entity_lives'],true))$value=absint($value);
   elseif($key==='serben_entity_email')$value=sanitize_email($value);
   elseif($key==='serben_entity_logo_url')$value=esc_url_raw($value);
   else $value=sanitize_text_field($value);
   if($key==='serben_partner_post_id' && $value && get_post_type($value)!=='serben_parceiro')continue;
   update_user_meta($user_id,$key,$value);
  }
 }

}
