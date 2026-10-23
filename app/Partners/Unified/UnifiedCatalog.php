<?php
namespace SerbenConnect\Partners\Unified;
if (!defined('ABSPATH')) exit;
final class UnifiedCatalog {
 public static function register():void {
  add_shortcode('serben_connect_catalogo', [__CLASS__,'catalog']);
  add_shortcode('serben_connect_parceiro', [__CLASS__,'single']);
  add_action('wp_enqueue_scripts',function(){wp_register_style('serben-unified-catalog',SERBEN_CONNECT_URL.'assets/css/unified-catalog.css',[],SERBEN_CONNECT_VERSION);});
  add_action('admin_menu',[__CLASS__,'menu'],30);
  add_action('admin_post_serben_connect_import',[__CLASS__,'import']);
 }
 public static function menu():void {add_submenu_page('serben-connect','Importação Connect','Importação Connect','manage_options','serben-connect-import',[__CLASS__,'page']);}
 public static function page():void {
  if(!current_user_can('manage_options'))return;
  echo '<div class="wrap"><h1>Importação de parceiros — Clube SerBen Connect</h1><p>Importa planilhas CSV/XLSX usando a lógica de compatibilidade do Clube de Benefícios. Faça backup antes de importar. Os parceiros são identificados pelos dados da planilha e podem ser atualizados.</p>';
  if(isset($_GET['csc_result'])) { $data=get_transient('csc_import_result_'.get_current_user_id());if(is_array($data))echo '<div class="notice notice-info"><p>'.esc_html(wp_json_encode($data,JSON_UNESCAPED_UNICODE)).'</p></div>'; }
  echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="serben_connect_import">';wp_nonce_field('serben_connect_import');
  echo '<p><input type="file" name="planilha" accept=".xlsx,.csv" required></p><p><label><input type="checkbox" name="update_existing" value="1" checked> Atualizar parceiros existentes</label></p><p><label><input type="checkbox" name="publish" value="1" checked> Publicar parceiros importados</label></p>';
  submit_button('Importar planilha');echo '</form><hr><h2>Elementor / Shortcodes</h2><p><code>[serben_connect_catalogo]</code> — catálogo responsivo; <code>[serben_connect_parceiro]</code> — perfil do parceiro na página individual.</p><p><strong>Importante:</strong> mantenha o plugin Clube de Benefícios ativo até validar todas as funções no Connect. Esta etapa não substitui ainda os templates, filtros avançados ou ferramentas administrativas do plugin antigo.</p></div>';
 }
 public static function import():void {
  if(!current_user_can('manage_options'))wp_die('Sem permissão',403);
  check_admin_referer('serben_connect_import');
  if(empty($_FILES['planilha'])||!is_array($_FILES['planilha']))wp_die('Arquivo ausente');
  $f=$_FILES['planilha'];$ext=strtolower(pathinfo(sanitize_file_name($f['name']??''),PATHINFO_EXTENSION));
  if(!in_array($ext,['xlsx','csv'],true)||!is_uploaded_file($f['tmp_name']??''))wp_die('Arquivo inválido');
  if(($f['size']??0)>15*1024*1024)wp_die('Arquivo acima do limite de 15 MB');
  self::dependencies();
  try{$res=(new \CSC_Unified_Import_Excel())->import($f,['update_existing'=>!empty($_POST['update_existing']),'publish'=>!empty($_POST['publish'])]);}
  catch(\Throwable $e){wp_die('Falha na importação: '.esc_html($e->getMessage()));}
  set_transient('csc_import_result_'.get_current_user_id(),$res,600);
  wp_safe_redirect(admin_url('admin.php?page=serben-connect-import&csc_result=1'));exit;
 }
 private static function dependencies():void {
  if(!defined('SERBEN_POST_TYPE'))define('SERBEN_POST_TYPE','serben_parceiro');
  if(!defined('SERBEN_TAXONOMY'))define('SERBEN_TAXONOMY','serben_categoria');
  if(!defined('SERBEN_META_PREFIX'))define('SERBEN_META_PREFIX','_serben_');
  if(!defined('SERBEN_PLUGIN_DIR'))define('SERBEN_PLUGIN_DIR',SERBEN_CONNECT_PATH);
  foreach(['LegacyUtils.php','LegacyBranches.php','LegacyImporter.php'] as $f)require_once __DIR__.'/'.$f;
 }
 public static function catalog($atts=[]):string {
  $a=shortcode_atts(['limite'=>12,'categoria'=>'','titulo'=>'Parceiros e benefícios'],$atts);
  wp_enqueue_style('serben-unified-catalog');
  $page=max(1,(int)($_GET['csc_pagina']??1));$search=isset($_GET['csc_busca'])?sanitize_text_field(wp_unslash($_GET['csc_busca'])):'';
  $args=['post_type'=>'serben_parceiro','post_status'=>'publish','posts_per_page'=>min(48,max(1,(int)$a['limite'])),'paged'=>$page,'s'=>$search];
  if($a['categoria']!=='')$args['tax_query']=[['taxonomy'=>'serben_categoria','field'=>'slug','terms'=>sanitize_title($a['categoria'])]];
  $q=new \WP_Query($args);ob_start();echo '<section class="csc-catalog"><div class="csc-heading"><h2>'.esc_html($a['titulo']).'</h2><form method="get"><input name="csc_busca" type="search" value="'.esc_attr($search).'" placeholder="Buscar parceiros..."><button type="submit">Buscar</button></form></div><div class="csc-grid">';
  while($q->have_posts()){$q->the_post();$id=get_the_ID();$img=get_the_post_thumbnail_url($id,'medium_large');$logo=(int)get_post_meta($id,'_serben_logo_id',true);$benefit=get_post_meta($id,'_serben_desconto',true);if(!$benefit)$benefit=get_post_meta($id,'_serben_desconto_online',true);
   echo '<article class="csc-card"><a href="'.esc_url(get_permalink()).'" class="csc-photo">';if($img)echo '<img loading="lazy" src="'.esc_url($img).'" alt="'.esc_attr(get_the_title()).'">';else echo '<span class="csc-no-photo">Clube SerBen</span>';echo '</a><div class="csc-card-body">';
   if($logo)echo wp_get_attachment_image($logo,'thumbnail',false,['class'=>'csc-logo','loading'=>'lazy']);echo '<h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3>';
   $terms=get_the_terms($id,'serben_categoria');if(is_array($terms)&&$terms)echo '<p class="csc-category">'.esc_html($terms[0]->name).'</p>';if($benefit)echo '<p class="csc-benefit">'.esc_html($benefit).'</p>';echo '<a class="csc-link" href="'.esc_url(get_permalink()).'">Ver benefício →</a></div></article>';
  }wp_reset_postdata();echo '</div>';
  if(!$q->found_posts)echo '<p>Nenhum parceiro encontrado.</p>';
  if($q->max_num_pages>1)echo '<nav class="csc-pages">'.wp_kses_post(paginate_links(['base'=>add_query_arg('csc_pagina','%#%'),'format'=>'','current'=>$page,'total'=>$q->max_num_pages])).'</nav>';
  echo '</section>';return ob_get_clean();
 }
 public static function single($atts=[]):string {
  $a=shortcode_atts(['id'=>0],$atts);$id=(int)$a['id'];if(!$id&&is_singular('serben_parceiro'))$id=get_queried_object_id();$p=get_post($id);if(!$p||$p->post_type!=='serben_parceiro'||$p->post_status!=='publish')return '';
  wp_enqueue_style('serben-unified-catalog');$cover=get_the_post_thumbnail_url($id,'full');$benefit=get_post_meta($id,'_serben_desconto',true)?:get_post_meta($id,'_serben_desconto_online',true);$address=get_post_meta($id,'_serben_endereco',true);$site=get_post_meta($id,'_serben_link_parceiro',true);
  ob_start();echo '<article class="csc-profile">';if($cover)echo '<div class="csc-hero" style="background-image:url('.esc_url($cover).')"></div>';echo '<div class="csc-profile-body"><h1>'.esc_html(get_the_title($id)).'</h1>';if($benefit)echo '<strong class="csc-benefit">'.esc_html($benefit).'</strong>';echo '<div class="csc-description">'.wp_kses_post(wpautop($p->post_content)).'</div>';if($address)echo '<p>📍 '.esc_html($address).'</p>';if($site && !get_post_meta($id,'awin_advertiser_id',true) && !get_post_meta($id,'_serben_awin_advertiser_id',true))echo '<p><a class="csc-link" href="'.esc_url($site).'" rel="nofollow sponsored" target="_blank">Visitar parceiro ↗</a></p>';echo '</div></article>';return ob_get_clean();
 }
}
