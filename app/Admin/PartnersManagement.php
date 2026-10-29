<?php
namespace SerbenConnect\Admin;
if (!defined('ABSPATH')) exit;

final class PartnersManagement {
    private const FIELDS = [
        '_serben_desconto' => 'Benefício / desconto',
        '_serben_desconto_online' => 'Benefício online',
        '_serben_endereco' => 'Endereço',
        '_serben_link_parceiro' => 'Site / link do parceiro',
        '_serben_logo_id' => 'ID da imagem do logotipo',
        '_serben_telefone' => 'Telefone',
        '_serben_whatsapp' => 'WhatsApp',
        '_serben_cnpj' => 'CNPJ',
        'awin_advertiser_id' => 'AWIN advertiser ID',
        '_serben_awin_advertiser_id' => 'AWIN advertiser ID (legado)',
    ];
    public static function register(): void {
        add_action('init', [__CLASS__, 'ensureType'], 30);
        add_action('admin_menu', [__CLASS__, 'menu'], 35);
        add_action('admin_post_serben_partner_save', [__CLASS__, 'save']);
        add_action('admin_post_serben_partner_export', [__CLASS__, 'export']);
    }
    public static function ensureType(): void {
        if (!post_type_exists('serben_parceiro')) {
            register_post_type('serben_parceiro', [
                'labels'=>['name'=>'Parceiros SerBen','singular_name'=>'Parceiro SerBen'],
                'public'=>true,'show_ui'=>true,'show_in_menu'=>false,
                'supports'=>['title','editor','thumbnail','excerpt'], 'has_archive'=>false,
                'rewrite'=>['slug'=>'parceiro'],
            ]);
        }
        if (!taxonomy_exists('serben_categoria')) {
            register_taxonomy('serben_categoria', ['serben_parceiro'], [
                'labels'=>['name'=>'Categorias de parceiros'], 'public'=>true,
                'show_ui'=>true,'hierarchical'=>true,'show_admin_column'=>true,
            ]);
        } else {
            register_taxonomy_for_object_type('serben_categoria', 'serben_parceiro');
        }
    }
    public static function menu(): void {
        add_submenu_page('serben-connect','Gerenciar parceiros','Gerenciar parceiros','manage_options','serben-connect-partners',[__CLASS__,'page']);
    }
    private static function url(array $args=[]): string {
        return add_query_arg(array_merge(['page'=>'serben-connect-partners'],$args), admin_url('admin.php'));
    }
    public static function page(): void {
        if (!current_user_can('manage_options')) wp_die('Acesso negado',403);
        $id=isset($_GET['edit'])?absint($_GET['edit']):0;
        $post=$id?get_post($id):null;
        if ($id && (!$post || $post->post_type!=='serben_parceiro')) wp_die('Parceiro inválido',404);
        echo '<div class="wrap"><h1>Gerenciar parceiros</h1><p>Edite os parceiros cadastrados manualmente ou exporte a base em CSV. A exportação inclui campos personalizados e categorias. Não altera dados na API SerBen.</p>';
        if (isset($_GET['saved'])) echo '<div class="notice notice-success"><p>Parceiro salvo.</p></div>';
        if (isset($_GET['edit'])) {
            echo '<p><a href="'.esc_url(self::url()).'">← Voltar à lista</a></p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('serben_partner_save');
            echo '<input type="hidden" name="action" value="serben_partner_save"><input type="hidden" name="partner_id" value="'.(int)$id.'">';
            echo '<table class="form-table"><tbody>';
            self::field('Nome do parceiro','partner_title',$post?$post->post_title:'',true);
            echo '<tr><th>Descrição</th><td><textarea name="partner_content" rows="6" class="large-text">'.esc_textarea($post?$post->post_content:'').'</textarea></td></tr>';
            echo '<tr><th>Status</th><td><select name="partner_status"><option value="publish" '.selected($post?$post->post_status:'publish','publish',false).'>Publicado</option><option value="draft" '.selected($post?$post->post_status:'publish','draft',false).'>Rascunho</option></select></td></tr>';
            foreach (self::FIELDS as $key=>$label) self::field($label,'meta['.$key.']',$post?get_post_meta($id,$key,true):'');
            $terms=$post?wp_get_post_terms($id,'serben_categoria',['fields'=>'names']):[];
            self::field('Categorias (separadas por vírgula)','categories',is_wp_error($terms)?'':implode(', ',$terms));
            echo '</tbody></table>';
            submit_button($id?'Salvar parceiro':'Criar parceiro');echo '</form>';
            if($id) echo '<p><a href="'.esc_url(get_edit_post_link($id)).'">Abrir editor completo do WordPress (imagens e outros campos)</a></p>';
        } else {
            $search=isset($_GET['s'])?sanitize_text_field(wp_unslash($_GET['s'])):'';
            $status=isset($_GET['status'])?sanitize_key($_GET['status']):'any';
            if(!in_array($status,['any','publish','draft'],true))$status='any';
            $paged=max(1,isset($_GET['paged'])?absint($_GET['paged']):1);
            echo '<p><a class="button button-primary" href="'.esc_url(self::url(['edit'=>0])).'">Adicionar parceiro</a> ';
            echo '<a class="button" href="'.esc_url(wp_nonce_url(add_query_arg(['action'=>'serben_partner_export','s'=>$search,'status'=>$status],admin_url('admin-post.php')),'serben_partner_export')).'">Exportar CSV (filtro atual)</a></p>';
            echo '<form method="get"><input type="hidden" name="page" value="serben-connect-partners"><input type="search" name="s" value="'.esc_attr($search).'" placeholder="Buscar parceiro"> <select name="status"><option value="any">Todos os status</option><option value="publish" '.selected($status,'publish',false).'>Publicados</option><option value="draft" '.selected($status,'draft',false).'>Rascunhos</option></select> <button class="button">Filtrar</button></form>';
            $query=new \WP_Query(['post_type'=>'serben_parceiro','post_status'=>$status==='any'?['publish','draft','pending','private','future']:$status,'posts_per_page'=>30,'paged'=>$paged,'s'=>$search,'orderby'=>'title','order'=>'ASC']);
            echo '<table class="widefat striped"><thead><tr><th>Parceiro</th><th>Status</th><th>Benefício</th><th>Categorias</th><th>Ações</th></tr></thead><tbody>';
            foreach($query->posts as $p){
                $cats=wp_get_post_terms($p->ID,'serben_categoria',['fields'=>'names']);
                echo '<tr><td>'.esc_html($p->post_title).'</td><td>'.esc_html($p->post_status).'</td><td>'.esc_html((string)get_post_meta($p->ID,'_serben_desconto',true)).'</td><td>'.esc_html(is_wp_error($cats)?'':implode(', ',$cats)).'</td><td><a href="'.esc_url(self::url(['edit'=>$p->ID])).'">Editar</a> | <a href="'.esc_url(get_edit_post_link($p->ID)).'">Editor completo</a></td></tr>';
            }
            if(!$query->posts)echo '<tr><td colspan="5">Nenhum parceiro encontrado.</td></tr>';
            echo '</tbody></table>';
            if($query->max_num_pages>1)echo '<p>'.wp_kses_post(paginate_links(['base'=>add_query_arg('paged','%#%',self::url(['s'=>$search,'status'=>$status])),'format'=>'','current'=>$paged,'total'=>$query->max_num_pages])).'</p>';
        }
        echo '</div>';
    }
    private static function field(string $label,string $name,$value,bool $required=false):void {
        echo '<tr><th><label>'.esc_html($label).'</label></th><td><input class="regular-text" type="text" name="'.esc_attr($name).'" value="'.esc_attr(is_scalar($value)?(string)$value:'').'" '.($required?'required':'').'></td></tr>';
    }
    public static function save():void {
        if(!current_user_can('manage_options'))wp_die('Acesso negado',403);
        check_admin_referer('serben_partner_save');
        $id=absint($_POST['partner_id']??0);
        if($id && get_post_type($id)!=='serben_parceiro')wp_die('Parceiro inválido',404);
        $title=sanitize_text_field(wp_unslash($_POST['partner_title']??''));
        if($title==='')wp_die('Nome obrigatório',400);
        $data=['post_type'=>'serben_parceiro','post_title'=>$title,'post_content'=>wp_kses_post(wp_unslash($_POST['partner_content']??'')),'post_status'=>($_POST['partner_status']??'')==='draft'?'draft':'publish'];
        if($id)$data['ID']=$id;
        $saved=wp_insert_post($data,true);
        if(is_wp_error($saved))wp_die(esc_html($saved->get_error_message()));
        $meta=isset($_POST['meta'])&&is_array($_POST['meta'])?wp_unslash($_POST['meta']):[];
        foreach(self::FIELDS as $key=>$label){
            $value=isset($meta[$key])&&is_scalar($meta[$key])?(string)$meta[$key]:'';
            $value=$key==='_serben_link_parceiro'?esc_url_raw($value):sanitize_text_field($value);
            if($value==='')delete_post_meta($saved,$key);else update_post_meta($saved,$key,$value);
        }
        $cats=sanitize_text_field(wp_unslash($_POST['categories']??''));
        wp_set_object_terms($saved,array_values(array_filter(array_map('trim',explode(',',$cats)))),'serben_categoria',false);
        wp_safe_redirect(self::url(['edit'=>$saved,'saved'=>1]));exit;
    }
    public static function export():void {
        if(!current_user_can('manage_options'))wp_die('Acesso negado',403);
        check_admin_referer('serben_partner_export');
        $search=isset($_GET['s'])?sanitize_text_field(wp_unslash($_GET['s'])):'';
        $status=isset($_GET['status'])?sanitize_key($_GET['status']):'any';
        if(!in_array($status,['any','publish','draft'],true))$status='any';
        $ids=get_posts(['post_type'=>'serben_parceiro','post_status'=>$status==='any'?['publish','draft','pending','private','future']:$status,'posts_per_page'=>-1,'fields'=>'ids','s'=>$search,'orderby'=>'title','order'=>'ASC']);
        $keys=[];
        foreach($ids as $id)foreach(array_keys(get_post_meta($id)) as $key)$keys[$key]=true;
        $keys=array_keys($keys);sort($keys,SORT_STRING);
        nocache_headers();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="serben-parceiros-'.gmdate('Y-m-d').'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,array_merge(['ID','Nome','Status','Descrição','Categorias','URL','Imagem destacada ID'],$keys),';');
        foreach($ids as $id){
            $p=get_post($id);$cats=wp_get_post_terms($id,'serben_categoria',['fields'=>'names']);
            $row=[$id,$p->post_title,$p->post_status,$p->post_content,is_wp_error($cats)?'':implode(' | ',$cats),get_permalink($id),get_post_thumbnail_id($id)];
            foreach($keys as $key){$v=get_post_meta($id,$key,true);$row[]=is_scalar($v)?(string)$v:wp_json_encode($v,JSON_UNESCAPED_UNICODE);}
            $row=array_map(static function($v){$v=(string)$v;return preg_match('/^[\s]*[=+@\-]/u',$v)?"'".$v:$v;},$row);
            fputcsv($out,$row,';');
        }
        fclose($out);exit;
    }
}
