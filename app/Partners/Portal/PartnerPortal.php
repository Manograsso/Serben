<?php
namespace SerbenConnect\Partners\Portal;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class PartnerPortal
{
    private const ALLOWED_FIELDS = [
        'nome_fantasia','whatsapp','site','instagram','cidade','estado','cep',
        'descricao_curta','descricao_completa','logo','foto_capa','galeria'
    ];

    public function register(): void
    {
        add_shortcode('serben_partner_dashboard', [$this, 'dashboard']);
        add_shortcode('serben_partner_profile', [$this, 'profile']);
        add_shortcode('serben_partner_plan', [$this, 'plan']);
        add_shortcode('serben_partner_subscription_status', [$this, 'subscriptionStatus']);
        add_shortcode('serben_partner_edit_store', [$this, 'editStore']);
        add_shortcode('serben_partner_logout', [$this, 'logout']);
        add_action('admin_post_serben_partner_update_store', [$this, 'handleUpdate']);
    }

    public function dashboard(): string
    {
        $context = $this->context();
        if (!$context['ok']) { return $context['message']; }
        $postId = $context['post_id'];
        $name = get_post_meta($postId, 'nome_fantasia', true) ?: get_the_title($postId);
        $plan = get_user_meta(get_current_user_id(), 'serben_partner_plan_name', true) ?: 'Não informado';
        $status = get_user_meta(get_current_user_id(), 'serben_partner_subscription_status', true) ?: 'Pendente de sincronização';
        $editUrl = get_edit_post_link($postId, '');
        $html = '<div class="serben-partner-dashboard">';
        $html .= '<h2>Olá, ' . esc_html($name) . '</h2>';
        $html .= '<div class="serben-component-grid">';
        $html .= $this->card('Minha loja', get_the_title($postId), $editUrl ? '<a href="'.esc_url($editUrl).'">Abrir cadastro</a>' : '');
        $html .= $this->card('Plano', $plan, '');
        $html .= $this->card('Assinatura', $status, '');
        $html .= $this->card('CNPJ', $this->formatCnpj((string)get_user_meta(get_current_user_id(), 'serben_partner_cnpj', true)), '');
        $html .= '</div></div>';
        return $html;
    }

    public function profile(): string
    {
        $context = $this->context();
        if (!$context['ok']) { return $context['message']; }
        $id=$context['post_id'];
        $fields=['nome_fantasia'=>'Nome fantasia','whatsapp'=>'WhatsApp','site'=>'Site','instagram'=>'Instagram','cidade'=>'Cidade','estado'=>'Estado','cep'=>'CEP','descricao_curta'=>'Descrição curta'];
        $html='<div class="serben-partner-profile"><dl>';
        foreach($fields as $key=>$label){
            $value=get_post_meta($id,$key,true);
            if($key==='nome_fantasia' && !$value){$value=get_the_title($id);} 
            $html.='<dt><strong>'.esc_html($label).'</strong></dt><dd>'.esc_html(is_scalar($value)?(string)$value:'').'</dd>';
        }
        return $html.'</dl></div>';
    }

    public function plan(): string
    {
        if (!$this->isPartner()) { return $this->loginMessage(); }
        $name=(string)get_user_meta(get_current_user_id(),'serben_partner_plan_name',true);
        $id=(string)get_user_meta(get_current_user_id(),'serben_partner_plan_id',true);
        return $this->card('Plano do lojista', $name ?: 'Ainda não sincronizado', $id ? 'ID do plano: '.esc_html($id) : '');
    }

    public function subscriptionStatus(): string
    {
        if (!$this->isPartner()) { return $this->loginMessage(); }
        $status=(string)get_user_meta(get_current_user_id(),'serben_partner_subscription_status',true);
        $next=(string)get_user_meta(get_current_user_id(),'serben_partner_next_due_date',true);
        return $this->card('Status da assinatura', $status ?: 'Pendente de sincronização', $next ? 'Próximo vencimento: '.esc_html($next) : '');
    }

    public function editStore(): string
    {
        $context=$this->context();
        if(!$context['ok']){return $context['message'];}
        $id=$context['post_id'];
        $labels=['nome_fantasia'=>'Nome fantasia','whatsapp'=>'WhatsApp','site'=>'Site','instagram'=>'Instagram','cidade'=>'Cidade','estado'=>'Estado','cep'=>'CEP','descricao_curta'=>'Descrição curta','descricao_completa'=>'Descrição completa'];
        $html='';
        if(isset($_GET['serben_partner_updated'])){$html.='<div class="serben-message serben-success">Dados atualizados.</div>';}
        $html.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="serben-form serben-partner-edit-form">';
        $html.='<input type="hidden" name="action" value="serben_partner_update_store">';
        $html.='<input type="hidden" name="post_id" value="'.(int)$id.'">';
        $html.=wp_nonce_field('serben_partner_update_store','serben_partner_nonce',true,false);
        foreach($labels as $key=>$label){
            $value=get_post_meta($id,$key,true);
            if($key==='nome_fantasia'&&!$value){$value=get_the_title($id);} 
            $html.='<p><label><strong>'.esc_html($label).'</strong><br>';
            if(strpos($key,'descricao_')===0){$html.='<textarea name="fields['.esc_attr($key).']" rows="5">'.esc_textarea((string)$value).'</textarea>';}
            else{$html.='<input type="text" name="fields['.esc_attr($key).']" value="'.esc_attr((string)$value).'">';}
            $html.='</label></p>';
        }
        $html.='<button type="submit" class="serben-button">Salvar dados da loja</button></form>';
        return $html;
    }

    public function handleUpdate(): void
    {
        if(!$this->isPartner()){wp_die('Acesso negado.');}
        if(!isset($_POST['serben_partner_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['serben_partner_nonce'])),'serben_partner_update_store')){wp_die('Requisição inválida.');}
        $linked=(int)get_user_meta(get_current_user_id(),'serben_partner_post_id',true);
        $postId=absint($_POST['post_id']??0);
        if(!$linked||$linked!==$postId){wp_die('Você não pode editar este parceiro.');}
        $fields=is_array($_POST['fields']??null)?wp_unslash($_POST['fields']):[];
        foreach(self::ALLOWED_FIELDS as $key){
            if(!array_key_exists($key,$fields)){continue;}
            $value=in_array($key,['descricao_curta','descricao_completa'],true)?sanitize_textarea_field($fields[$key]):sanitize_text_field($fields[$key]);
            update_post_meta($postId,$key,$value);
            if($key==='nome_fantasia'&&$value!==''){wp_update_post(['ID'=>$postId,'post_title'=>$value]);}
        }
        do_action('serben_partner_profile_updated',get_current_user_id(),$postId,$fields);
        $redirect=wp_get_referer()?:home_url('/');
        wp_safe_redirect(add_query_arg('serben_partner_updated','1',$redirect));exit;
    }

    public function logout($atts=[]): string
    {
        $atts=shortcode_atts(['text'=>'Sair','redirect'=>''],is_array($atts)?$atts:[]);
        if(!is_user_logged_in()){return '';}
        $redirect=$atts['redirect']?esc_url_raw($atts['redirect']):home_url('/');
        return '<a class="serben-button serben-partner-logout" href="'.esc_url(wp_logout_url($redirect)).'">'.esc_html($atts['text']).'</a>';
    }

    private function context(): array
    {
        if(!$this->isPartner()){return ['ok'=>false,'message'=>$this->loginMessage(),'post_id'=>0];}
        $postId=(int)get_user_meta(get_current_user_id(),'serben_partner_post_id',true);
        if(!$postId||!get_post($postId)){return ['ok'=>false,'message'=>'<div class="serben-message serben-error">Seu usuário ainda não está vinculado a um parceiro.</div>','post_id'=>0];}
        return ['ok'=>true,'message'=>'','post_id'=>$postId];
    }
    private function isPartner(): bool { $u=wp_get_current_user(); return $u->exists()&&in_array('serben_lojista',(array)$u->roles,true); }
    private function loginMessage(): string { return '<div class="serben-message">Faça login como parceiro para visualizar.</div>'; }
    private function card(string $label,string $value,string $description): string { return '<div class="serben-component-card"><span class="serben-component-label">'.esc_html($label).'</span><strong class="serben-component-value">'.esc_html($value?:'—').'</strong>'.($description?'<small>'.$description.'</small>':'').'</div>'; }
    private function formatCnpj(string $v): string { $v=preg_replace('/\D+/','',$v); return strlen($v)===14?substr($v,0,2).'.'.substr($v,2,3).'.'.substr($v,5,3).'/'.substr($v,8,4).'-'.substr($v,12,2):$v; }
}
