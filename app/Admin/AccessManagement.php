<?php
namespace SerbenConnect\Admin;

use SerbenConnect\Partners\Auth\PartnerLinker;

if (!defined('ABSPATH')) { exit; }

/** Access management integrated into Clube Serben Connect (no independent plugin). */
final class AccessManagement {
    const OPTION = 'serben_access_requests';

    public function register(): void {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_nopriv_serben_access_request', [$this, 'request']);
        add_action('admin_post_serben_access_request', [$this, 'request']);
        add_action('admin_post_serben_access_invite', [$this, 'invite']);
        add_action('admin_post_serben_access_decide', [$this, 'decide']);
        add_action('admin_post_serben_access_revoke', [$this, 'revoke']);
        add_action('admin_post_serben_access_restore', [$this, 'restore']);
        add_action('admin_post_serben_access_resend', [$this, 'resend']);
        add_action('admin_post_serben_access_bind_entity', [$this, 'bindEntity']);
        add_filter('wp_authenticate_user', [$this, 'verifyManagedLogin'], 20, 2);
        add_shortcode('serben_access_request', [$this, 'form']);
    }
    public function menu(): void {
        add_submenu_page('serben-connect', 'Gestão de acessos', 'Gestão de acessos', 'manage_options', 'serben-access', [$this,'page']);
    }
    private function url(string $notice = ''): string {
        $url = admin_url('admin.php?page=serben-access');
        return $notice ? add_query_arg('notice', $notice, $url) : $url;
    }
    private function records(): array { $records = get_option(self::OPTION, []); return is_array($records) ? $records : []; }
    private function save(array $records): void { update_option(self::OPTION, $records, false); }
    private function cnpj(string $value): string { return preg_replace('/\D+/', '', $value); }
    private function validCnpj(string $digits): bool {
        if (!preg_match('/^\d{14}$/', $digits) || preg_match('/^(\d)\1{13}$/', $digits)) { return false; }
        for ($n=12; $n<=13; $n++) {
            $sum=0; $weight=$n===12?5:6;
            for ($i=0; $i<$n; $i++) { $sum+=(int)$digits[$i]*$weight; $weight=$weight===2?9:$weight-1; }
            $digit=$sum%11; $digit=$digit<2?0:11-$digit;
            if ((int)$digits[$n] !== $digit) { return false; }
        }
        return true;
    }
    public static function accountAllowed(int $userId): bool {
        // Do not retrospectively lock out accounts created before the approval workflow.
        // Managed accounts fail closed unless explicitly approved.
        if (!$userId || !get_user_meta($userId, 'serben_access_managed', true)) { return true; }
        return (string)get_user_meta($userId, 'serben_access_approved', true) === '1';
    }
    public function verifyManagedLogin($user, $password) {
        if ($user instanceof \WP_User && !self::accountAllowed((int)$user->ID)) {
            return new \WP_Error('serben_access_inactive', 'Acesso não aprovado ou revogado. Contate a administração SerBen.');
        }
        return $user;
    }
    private function assertAdmin(string $action, string $id = ''): void {
        if (!current_user_can('manage_options')) { wp_die('Acesso administrativo restrito.', 'Acesso restrito', ['response'=>403]); }
        check_admin_referer($action.$id);
    }
    private function post(string $field): string {
        $value = isset($_POST[$field]) && is_scalar($_POST[$field]) ? (string)wp_unslash($_POST[$field]) : '';
        return sanitize_text_field($value);
    }
    public function form(): string {
        $notice = isset($_GET['serben_access_status']) ? sanitize_key(wp_unslash($_GET['serben_access_status'])) : '';
        $out = '<section class="serben-box"><h3>Solicitar acesso de parceiro ou entidade</h3><p>O pedido será analisado pela equipe SerBen. O CNPJ informado não garante a aprovação.</p>';
        if ($notice === 'received') { $out .= '<p role="status" class="serben-alert serben-success">Solicitação recebida. Aguarde análise administrativa.</p>'; }
        if ($notice === 'pending') { $out .= '<p role="status" class="serben-alert serben-success">Já existe uma solicitação pendente para este CNPJ e e-mail. Aguarde a análise administrativa.</p>'; }
        if ($notice === 'limited') { $out .= '<p role="alert" class="serben-alert serben-error">Aguarde alguns minutos antes de enviar outra solicitação.</p>'; }
        if ($notice === 'invalid') { $out .= '<p role="alert" class="serben-alert serben-error">Confira os dados e tente novamente.</p>'; }
        $out .= '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        $out .= '<input type="hidden" name="serben_access_return" value="'.esc_url(remove_query_arg('serben_access_status', get_permalink() ?: home_url('/'))).'">';
        $out .= '<input type="hidden" name="action" value="serben_access_request">'.wp_nonce_field('serben_access_request','serben_access_nonce',true,false);
        $out .= '<p><label>Tipo <select name="access_type" required><option value="partner">Parceiro</option><option value="entity">Entidade</option></select></label></p>';
        $out .= '<p><label>CNPJ <input name="cnpj" required inputmode="numeric" maxlength="18"></label></p>';
        $out .= '<p><label>Nome do responsável <input name="full_name" required maxlength="120"></label></p>';
        $out .= '<p><label>E-mail <input type="email" name="email" required></label></p>';
        $out .= '<p style="position:absolute;left:-9999px" aria-hidden="true"><label>Website <input name="company_website" tabindex="-1" autocomplete="off"></label></p>';
        return $out.'<button type="submit">Enviar solicitação</button></form></section>';
    }
    private function submitted(): array {
        return [
            'cnpj'=>$this->cnpj($this->post('cnpj')),
            'email'=>sanitize_email($this->post('email')),
            'name'=>$this->post('full_name'),
            'type'=>sanitize_key($this->post('access_type')),
        ];
    }
    private function validRecord(array $item): bool {
        return $this->validCnpj($item['cnpj']) && is_email($item['email']) && $item['name'] !== '' && in_array($item['type'], ['partner','entity'], true);
    }
    private function putNew(array $item, string $source): bool {
        $records=$this->records();
        foreach ($records as $old) {
            if (($old['email']??'') === $item['email'] && ($old['cnpj']??'') === $item['cnpj'] && ($old['status']??'') === 'pending' && ($old['type']??'') === $item['type']) { return false; }
        }
        $id=wp_generate_uuid4();
        $records[$id]=array_merge($item, [
            'id'=>$id, 'status'=>'pending','source'=>$source,'created'=>current_time('mysql',true)
        ]);
        $this->save($records);
        return true;
    }
    private function redirectRequest(string $status, string $ref): void {
        nocache_headers();
        wp_safe_redirect(add_query_arg(['serben_access_status'=>$status,'serben_access_check'=>time()],remove_query_arg(['serben_access_status','serben_access_check'],$ref)),303);
        exit;
    }
    public function request(): void {
        if (($_SERVER['REQUEST_METHOD']??'') !== 'POST') { wp_die('Método inválido.', '', ['response'=>405]); }
        $nonce=$this->post('serben_access_nonce');
        if (!wp_verify_nonce($nonce,'serben_access_request')) { wp_die('Atualize o formulário e tente novamente.', '', ['response'=>403]); }
        $ref=wp_validate_redirect(esc_url_raw($this->post('serben_access_return')), home_url('/'));
        if (!$ref) { $ref=home_url('/'); }
        if ($this->post('company_website') !== '') { $this->redirectRequest('received',$ref); }
        $item=$this->submitted();
        if (!$this->validRecord($item)) { $this->redirectRequest('invalid',$ref); }
        foreach ($this->records() as $old) {
            if (($old['email']??'') === $item['email'] && ($old['cnpj']??'') === $item['cnpj']
                && ($old['type']??'') === $item['type'] && ($old['status']??'') === 'pending') {
                $this->redirectRequest('pending',$ref);
            }
        }
        $key='serben_access_rate_'.md5((string)($_SERVER['REMOTE_ADDR']??'unknown').'|'.$item['email']);
        if (get_transient($key)) { $this->redirectRequest('limited',$ref); }
        if (!$this->putNew($item,'request')) { $this->redirectRequest('pending',$ref); }
        set_transient($key,1,10*MINUTE_IN_SECONDS);
        wp_mail(get_option('admin_email'),'SerBen: nova solicitação de acesso','Nova solicitação aguardando aprovação: '.$this->url());
        $this->redirectRequest('received',$ref);
    }
    public function invite(): void {
        $this->assertAdmin('serben_access_invite');
        $item=$this->submitted();
        if (!$this->validRecord($item)) { wp_safe_redirect($this->url('invalid'));exit; }
        $this->putNew($item,'invite');
        wp_safe_redirect($this->url('created'));exit;
    }
    private function emailPasswordLink(int $userId): bool {
        $user=get_userdata($userId);
        if (!$user) { return false; }
        $key=get_password_reset_key($user);
        if (is_wp_error($key)) { return false; }
        $link=network_site_url('wp-login.php?action=rp&key='.rawurlencode($key).'&login='.rawurlencode($user->user_login),'login');
        return (bool)wp_mail($user->user_email,'Acesso ao Clube SerBen',"Seu acesso foi autorizado. Para definir sua senha, use este link pessoal e de validade limitada:\n".$link."\nSe não solicitou o acesso, ignore esta mensagem.");
    }
    /** A partner must be uniquely present in the local catalogue before access is issued. */
    private function partnerPostId(string $cnpj): int {
        $type = sanitize_key((string)\SerbenConnect\Support\Settings::get('partners_post_type', 'serben_parceiro'));
        $ids = get_posts(['post_type'=>$type, 'post_status'=>['publish','draft','pending','private'], 'posts_per_page'=>-1, 'fields'=>'ids']);
        $matches = [];
        foreach ($ids as $postId) {
            foreach (['cnpj_do_parceiro','_serben_cnpj'] as $field) {
                if ($this->cnpj((string)get_post_meta((int)$postId,$field,true)) === $cnpj) {
                    $matches[(int)$postId] = true;
                    break;
                }
            }
        }
        return count($matches) === 1 ? (int)array_key_first($matches) : 0;
    }
    /** Only reuse an entity ID when an existing authorized record corroborates the CNPJ. */
    private function verifiedEntityId(int $entityId, string $cnpj): bool {
        if (!$entityId) { return false; }
        $users = get_users(['meta_key'=>'serben_entity_id','meta_value'=>$entityId,'number'=>200,'fields'=>'all']);
        foreach ($users as $user) {
            if ($this->cnpj((string)get_user_meta($user->ID,'serben_entity_cnpj',true)) === $cnpj
                && self::accountAllowed((int)$user->ID)) { return true; }
        }
        return false;
    }
    private function entityIdConflict(int $entityId, string $cnpj): bool {
        if (!$entityId) { return false; }
        $users=get_users(['meta_key'=>'serben_entity_id','meta_value'=>$entityId,'number'=>100,'fields'=>'all']);
        foreach ($users as $user) {
            $other=$this->cnpj((string)get_user_meta($user->ID,'serben_entity_cnpj',true));
            if ($other && $other !== $cnpj) { return true; }
        }
        return false;
    }
    public function decide(): void {
        $id=$this->post('request_id');
        $this->assertAdmin('serben_access_decide_', $id);
        $decision=sanitize_key($this->post('decision'));
        $records=$this->records();
        if (!isset($records[$id]) || ($records[$id]['status']??'') !== 'pending') { wp_safe_redirect($this->url('not_found'));exit; }
        if ($decision === 'reject') {
            $records[$id]['status']='rejected'; $records[$id]['reviewer']=get_current_user_id();
            $this->save($records); wp_safe_redirect($this->url('updated'));exit;
        }
        if ($decision !== 'approve') { wp_safe_redirect($this->url('invalid'));exit; }
        $item=$records[$id];
        if (!$this->validRecord($item)) { wp_safe_redirect($this->url('invalid'));exit; }
        $entityId=$item['type']==='entity' ? absint($this->post('entity_id')) : 0;
        $partnerId=$item['type']==='partner' ? $this->partnerPostId($item['cnpj']) : 0;
        if ($item['type'] === 'partner' && !$partnerId) {
            wp_safe_redirect($this->url('partner_unlinked')); exit;
        }
        if ($entityId && ($this->entityIdConflict($entityId,$item['cnpj']) || !$this->verifiedEntityId($entityId,$item['cnpj']))) {
            wp_safe_redirect($this->url('entity_unverified')); exit;
        }
        $login='serben_access_'.str_replace('-','',substr($id,0,18));
        $existing=get_user_by('email',$item['email']);
        $byLogin=get_user_by('login',$login);
        $userId=0;
        $recovered=false;
        if ($existing || $byLogin) {
            // A previously interrupted approval may have created the user already.
            // Never attach an unrelated WordPress account solely by matching email.
            if (!$existing || !$byLogin || (int)$existing->ID !== (int)$byLogin->ID
                || (string)$existing->user_email !== (string)$item['email']
                || !in_array($item['type']==='partner'?'serben_lojista':'serben_entidade', (array)$existing->roles, true)
                || (string)get_user_meta($existing->ID,'serben_access_managed',true) !== '1'
                || (string)get_user_meta($existing->ID,'serben_access_cnpj',true) !== $item['cnpj']
                || (string)get_user_meta($existing->ID,'serben_identity_type',true) !== $item['type']) {
                wp_safe_redirect($this->url('email_exists')); exit;
            }
            $userId=(int)$existing->ID;
            $recovered=true;
        } else {
            $userId=wp_insert_user([
                'user_login'=>$login,'user_email'=>$item['email'],'display_name'=>$item['name'],
                'user_pass'=>wp_generate_password(40,true,true),
                'role'=>$item['type']==='partner'?'serben_lojista':'serben_entidade',
            ]);
            if (is_wp_error($userId)) { wp_safe_redirect($this->url('create_error'));exit; }
            $userId=(int)$userId;
            // Immediately mark as managed and inactive before any external integration.
            update_user_meta($userId,'serben_access_managed',1);
            update_user_meta($userId,'serben_access_approved',0);
            update_user_meta($userId,'serben_access_request_id',$id);
            update_user_meta($userId,'serben_identity_type',$item['type']);
            update_user_meta($userId,'serben_documento',$item['cnpj']);
            update_user_meta($userId,'serben_access_cnpj',$item['cnpj']);
        }
        // Recovered accounts must be tied to the same request, or be a verified
        // legacy partial creation with the exact deterministic login and metadata.
        $owner=(string)get_user_meta($userId,'serben_access_request_id',true);
        if ($owner !== '' && $owner !== $id) {
            wp_safe_redirect($this->url('email_exists')); exit;
        }
        update_user_meta($userId,'serben_access_request_id',$id);
        if ($item['type'] === 'partner') {
            update_user_meta($userId,'serben_partner_cnpj',$item['cnpj']);
            try { $linked=(new PartnerLinker())->link($userId,$item['cnpj']); }
            catch (\Throwable $e) { $linked=['ok'=>false]; error_log('SerBen access partner link failed: '.$e->getMessage()); }
            if (empty($linked['ok'])) {
                // Keep an interrupted account inactive for a safe retry.
                update_user_meta($userId,'serben_access_approved',0);
                wp_safe_redirect($this->url('partner_unlinked')); exit;
            }
            $records[$id]['link_status']='linked';
        } else {
            update_user_meta($userId,'serben_entity_cnpj',$item['cnpj']);
            if ($entityId) { update_user_meta($userId,'serben_entity_id',$entityId); }
            $records[$id]['link_status']=$entityId?'linked':'pending';
        }
        // Persist the approval BEFORE sending email. A mail failure must not
        // leave an approved user with a pending request.
        $records[$id]['status']='approved';
        $records[$id]['user_id']=$userId;
        $records[$id]['entity_id']=$entityId;
        $records[$id]['reviewer']=get_current_user_id();
        $records[$id]['approved_at']=current_time('mysql',true);
        $records[$id]['email_sent']=false;
        $this->save($records);
        update_user_meta($userId,'serben_access_approved',1);
        try { $sent=$this->emailPasswordLink($userId); }
        catch (\Throwable $e) { $sent=false; error_log('SerBen access mail failed: '.$e->getMessage()); }
        $records[$id]['email_sent']=(bool)$sent;
        $this->save($records);
        wp_safe_redirect($this->url($sent?($recovered?'recovered':'updated'):'email_failed'));exit;
    }
    private function findApproved(string $id): array {
        $records=$this->records();
        if (!isset($records[$id])) { return [[],0,[]]; }
        $item=$records[$id]; $userId=absint($item['user_id']??0);
        if (!$userId || !get_userdata($userId)) { return [$records,0,$item]; }
        if ($this->cnpj((string)get_user_meta($userId,'serben_access_cnpj',true)) !== ($item['cnpj']??'')) { return [$records,0,$item]; }
        return [$records,$userId,$item];
    }
    /** Administrative binding: API confirms the CNPJ, not the institutional ID. */
    public function bindEntity(): void {
        $id=$this->post('request_id');
        $this->assertAdmin('serben_access_bind_entity_', $id);
        list($records,$userId,$item)=$this->findApproved($id);
        if (!$userId || ($item['status']??'')!=='approved' || ($item['type']??'')!=='entity'
            || (string)get_user_meta($userId,'serben_access_request_id',true)!==$id
            || (string)get_user_meta($userId,'serben_identity_type',true)!=='entity'
            || !self::accountAllowed($userId)) {
            wp_safe_redirect($this->url('not_found')); exit;
        }
        $entityId=absint($this->post('entity_id'));
        if (!$entityId || $this->post('confirm_manual')!=='1') {
            wp_safe_redirect($this->url('entity_confirmation_required')); exit;
        }
        $cnpj=$this->cnpj((string)($item['cnpj']??''));
        if (!$this->validCnpj($cnpj) || $this->entityIdConflict($entityId,$cnpj)) {
            wp_safe_redirect($this->url('entity_conflict')); exit;
        }
        // Manual administrative attestation. The modern API key cannot authenticate
        // Corporacao/getByCnpj, and no available endpoint proves entity ID ownership.
        // Never claim API verification of the CNPJ-to-ID relationship.
        $confirmedCnpj=$this->cnpj($this->post('confirmed_cnpj'));
        if ($confirmedCnpj !== $cnpj) {
            wp_safe_redirect($this->url('entity_cnpj_confirmation')); exit;
        }
        $entityName=$this->post('entity_name');
        if ($entityName === '') {
            wp_safe_redirect($this->url('entity_name_required')); exit;
        }
        // Check again immediately before writing, including pre-existing user links.
        if ($this->entityIdConflict($entityId,$cnpj)) { wp_safe_redirect($this->url('entity_conflict')); exit; }
        update_user_meta($userId,'serben_entity_cnpj',$cnpj);
        update_user_meta($userId,'serben_entity_id',$entityId);
        update_user_meta($userId,'serben_entity_name',$entityName);
        $records[$id]['entity_id']=$entityId;
        $records[$id]['link_status']='linked';
        $records[$id]['linked_by']=get_current_user_id();
        $records[$id]['linked_at']=current_time('mysql',true);
        $records[$id]['link_source']='admin_manual_attestation';
        $records[$id]['linked_cnpj']=$cnpj;
        $records[$id]['linked_entity_name']=$entityName;
        $this->save($records);
        wp_safe_redirect($this->url('entity_linked')); exit;
    }
    public function revoke(): void {
        $id=$this->post('request_id'); $this->assertAdmin('serben_access_revoke_', $id);
        list($records,$userId,$item)=$this->findApproved($id);
        if (!$userId || ($item['status']??'') !== 'approved') { wp_safe_redirect($this->url('not_found'));exit; }
        update_user_meta($userId,'serben_access_managed',1);
        update_user_meta($userId,'serben_access_approved',0);
        $records[$id]['status']='revoked'; $records[$id]['revoked_at']=current_time('mysql',true);
        $records[$id]['reviewer']=get_current_user_id(); $this->save($records);
        if (class_exists('WP_Session_Tokens')) { \WP_Session_Tokens::get_instance($userId)->destroy_all(); }
        wp_safe_redirect($this->url('revoked'));exit;
    }
    public function restore(): void {
        $id=$this->post('request_id'); $this->assertAdmin('serben_access_restore_', $id);
        list($records,$userId,$item)=$this->findApproved($id);
        if (!$userId || ($item['status']??'') !== 'revoked') { wp_safe_redirect($this->url('not_found'));exit; }
        update_user_meta($userId,'serben_access_managed',1);
        update_user_meta($userId,'serben_access_approved',1);
        $records[$id]['status']='approved'; $records[$id]['reviewer']=get_current_user_id();$this->save($records);
        wp_safe_redirect($this->url('restored'));exit;
    }
    public function resend(): void {
        $id=$this->post('request_id'); $this->assertAdmin('serben_access_resend_', $id);
        list($records,$userId,$item)=$this->findApproved($id);
        if (!$userId || ($item['status']??'') !== 'approved') { wp_safe_redirect($this->url('not_found'));exit; }
        $sent=$this->emailPasswordLink($userId); $records[$id]['email_sent']=$sent; $this->save($records);
        wp_safe_redirect($this->url($sent?'resent':'email_failed'));exit;
    }
    private function action(string $id, string $hook, string $label, string $style=''): string {
        $out='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:2px">';
        $out.='<input type="hidden" name="action" value="serben_access_'.esc_attr($hook).'">';
        $out.='<input type="hidden" name="request_id" value="'.esc_attr($id).'">';
        $out.=wp_nonce_field('serben_access_'.$hook.'_'.$id,'_wpnonce',true,false);
        return $out.'<button type="submit" class="button '.esc_attr($style).'">'.esc_html($label).'</button></form>';
    }
    public function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $notice=isset($_GET['notice'])?sanitize_key(wp_unslash($_GET['notice'])):'';
        $messages=[
            'created'=>'Convite registrado e aguardando aprovação.',
            'updated'=>'Solicitação atualizada.',
            'recovered'=>'Aprovação interrompida recuperada; conta existente vinculada à solicitação original.',
            'revoked'=>'Acesso revogado e sessões existentes encerradas.',
            'restored'=>'Acesso reativado.',
            'resent'=>'Novo link para definir senha enviado.',
            'email_failed'=>'Usuário criado, mas não foi possível confirmar o envio do e-mail. Verifique a entrega e tente reenviar.',
            'email_exists'=>'Esse e-mail já possui uma conta WordPress. Confirme a titularidade antes de associá-la; nenhuma permissão adicional foi concedida.',
            'entity_linked'=>'Entidade vinculada por conferência manual do administrador. Nenhuma verificação de ID foi feita pela API.',
            'entity_cnpj_confirmation'=>'O CNPJ digitado para confirmação não corresponde ao da solicitação. Vínculo não realizado.',
            'entity_name_required'=>'Informe a razão social conferida no cadastro SerBen.',
            'entity_confirmation_required'=>'Informe o ID institucional e confirme a conferência manual na SerBen.',
            'entity_conflict'=>'Este ID institucional já está vinculado a outro CNPJ. Nenhum acesso foi criado.',
            'entity_unverified'=>'ID institucional não validado contra um vínculo existente do mesmo CNPJ. A aprovação não foi realizada. Deixe o ID vazio para criar uma conta sem acesso aos dados da entidade.',
            'partner_unlinked'=>'A aprovação exige exatamente um parceiro cadastrado no catálogo com este CNPJ. Confira em Gerenciar parceiros (inclusive rascunhos) e corrija ausências ou duplicidades antes de aprovar.',
            'not_found'=>'Registro não encontrado ou situação incompatível.',
            'invalid'=>'Confira os dados e tente novamente.',
            'create_error'=>'Não foi possível criar o usuário. Confira os dados e os logs do WordPress.',
        ];
        echo '<div class="wrap"><h1>Gestão de acessos — SerBen Connect</h1>';
        if (isset($messages[$notice])) { echo '<div class="notice notice-info"><p>'.esc_html($messages[$notice]).'</p></div>'; }
        echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=serben-connect-partners')).'">Abrir Gerenciar parceiros</a></p>';
        echo '<p>Solicitações, convites, múltiplos responsáveis e revogações. <strong>Antes de aprovar, confirme documentalmente a representação e o CNPJ.</strong></p>';
        echo '<h2>Convidar responsável</h2><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo wp_nonce_field('serben_access_invite','_wpnonce',true,false);
        echo '<input type="hidden" name="action" value="serben_access_invite">';
        echo '<select name="access_type" aria-label="Tipo"><option value="partner">Parceiro</option><option value="entity">Entidade</option></select> ';
        echo '<input name="cnpj" placeholder="CNPJ" required> <input name="full_name" placeholder="Responsável" required> <input type="email" name="email" placeholder="E-mail" required> <button class="button button-primary">Criar convite</button></form>';
        echo '<h2>Solicitações e convites</h2><p>Parceiro sem vínculo de catálogo e entidade sem ID institucional aprovado não terão acesso aos dados específicos desses registros. Para entidades, aprove sem ID e depois utilize &quot;Conferir manualmente e vincular&quot;. Consulte o cadastro administrativo da SerBen e confirme CNPJ, razão social e ID institucional. Não há validação automática por API.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Origem / tipo</th><th>Responsável</th><th>E-mail</th><th>CNPJ</th><th>Status</th><th>Vínculo</th><th>Ações</th></tr></thead><tbody>';
        $records=array_slice(array_reverse($this->records(),true),0,100,true);
        foreach ($records as $id=>$item) {
            $status=(string)($item['status']??'');$type=(string)($item['type']??'');
            echo '<tr><td>'.esc_html((string)($item['source']??'').' / '.$type).'</td>';
            echo '<td>'.esc_html((string)($item['name']??'')).'</td>';
            echo '<td>'.esc_html((string)($item['email']??'')).'</td>';
            echo '<td>'.esc_html((string)($item['cnpj']??'')).'</td>';
            echo '<td><strong>'.esc_html($status).'</strong></td>';
            $linkStatus=(string)($item['link_status']??'—');
            echo '<td>'.esc_html($linkStatus).'</td><td>';
            if ($status==='pending') {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:2px">';
                echo wp_nonce_field('serben_access_decide_'.$id,'_wpnonce',true,false);
                echo '<input type="hidden" name="action" value="serben_access_decide"><input type="hidden" name="request_id" value="'.esc_attr($id).'">';
                echo '<input type="hidden" name="decision" value="approve">';
                if ($type==='entity') { echo '<label>ID institucional validado (opcional) <input type="number" min="1" name="entity_id" style="width:100px" aria-label="ID institucional"></label> '; }
                echo '<button type="submit" class="button button-primary">Aprovar</button></form>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:2px">';
                echo wp_nonce_field('serben_access_decide_'.$id,'_wpnonce',true,false);
                echo '<input type="hidden" name="action" value="serben_access_decide"><input type="hidden" name="request_id" value="'.esc_attr($id).'">';
                echo '<input type="hidden" name="decision" value="reject"><button class="button">Recusar</button></form>';
            } elseif ($status==='approved') {
                if ($type==='entity' && ($item['link_status']??'')!=='linked') {
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" style="display:inline-block;margin:2px">';
                    echo wp_nonce_field('serben_access_bind_entity_'.$id,'_wpnonce',true,false);
                    echo '<input type="hidden" name="action" value="serben_access_bind_entity"><input type="hidden" name="request_id" value="'.esc_attr($id).'">';
                    echo '<label>ID institucional <input type="number" min="1" required name="entity_id" style="width:105px"></label> ';
                    echo '<label>Razão social conferida <input type="text" name="entity_name" maxlength="180" required></label> ';
                    echo '<label>Redigite o CNPJ conferido <input type="text" name="confirmed_cnpj" maxlength="18" required></label> ';
                    echo '<label><input type="checkbox" name="confirm_manual" value="1" required> Conferi pessoalmente o CNPJ, a razão social e o ID institucional no cadastro administrativo da SerBen</label> ';
                    echo '<button type="submit" class="button button-primary">Conferir manualmente e vincular</button></form>';
                }
                echo $this->action($id,'revoke','Revogar');
                echo $this->action($id,'resend','Reenviar senha');
                if (!empty($item['user_id'])) { echo ' <a href="'.esc_url(get_edit_user_link((int)$item['user_id'])).'">Abrir usuário</a>'; }
            } elseif ($status==='revoked') { echo $this->action($id,'restore','Reativar'); }
            echo '</td></tr>';
        }
        if (!$records) { echo '<tr><td colspan="7">Nenhuma solicitação recebida.</td></tr>'; }
        echo '</tbody></table><p>Formulário público: <code>[serben_access_request]</code> · Login aprovado: <code>[serben_partner_login]</code>.</p></div>';
    }
}
