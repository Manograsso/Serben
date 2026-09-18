<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

/**
 * v1.6.0 - blocos composáveis para os dashboards Bume.
 * A camada visual usa dados WordPress e expõe filtros/actions para integrações financeiras.
 */
final class DashboardModulesShortcodes
{
    public function register(): void
    {
        $map = [
            'serben_bume_partner_overview'=>'partnerOverview',
            'serben_bume_partner_governance'=>'partnerGovernance',
            'serben_bume_partner_coupons'=>'partnerCoupons',
            'serben_bume_partner_wallet'=>'partnerWallet',
            'serben_bume_partner_recharge_packages'=>'partnerPackages',
            'serben_bume_partner_agency_hub'=>'partnerAgencyHub',
            'serben_entity_overview'=>'entityOverview',
            'serben_entity_governance'=>'entityGovernance',
            'serben_entity_wallet'=>'entityWallet',
            'serben_entity_statement'=>'entityStatement',
            'serben_entity_billing'=>'entityBilling',
            'serben_entity_benefits'=>'entityBenefits',
            'serben_entity_pricing'=>'entityPricing',
        ];
        foreach ($map as $tag=>$method) { add_shortcode($tag, [$this,$method]); }
        add_action('admin_post_serben_bume_partner_governance_save', [$this,'savePartnerGovernance']);
        add_action('admin_post_serben_entity_governance_save', [$this,'saveEntityGovernance']);
        add_action('admin_post_serben_bume_coupon_save', [$this,'saveCoupon']);
        add_action('init', [$this,'registerCouponPostType']);
    }

    public function registerCouponPostType(): void
    {
        register_post_type('serben_bume_cupom', [
            'label'=>'Cupons Bume','public'=>false,'show_ui'=>true,'show_in_menu'=>'serben-connect',
            'supports'=>['title','editor','author'],'capability_type'=>'post','map_meta_cap'=>true,
        ]);
    }

    public function partnerOverview(): string
    {
        $c=$this->partnerContext(); if(!$c['ok']) return $c['message'];
        $id=$c['post_id']; $uid=get_current_user_id(); $plan=$this->partnerPlan();
        $name=get_post_meta($id,'nome_fantasia',true) ?: get_the_title($id);
        $discount=(string)(get_post_meta($id,'cashback_geral',true) ?: get_post_meta($id,'desconto_base_vitrine',true) ?: '—');
        $wallet=$this->partnerWalletData();
        return $this->wrap('serben-bume-partner-overview','<h2>'.esc_html($name).'</h2><div class="serben-component-grid">'.
            $this->card('Plano',$plan).$this->card('Status',(string)(get_user_meta($uid,'serben_partner_subscription_status',true)?:'Ativo')).
            $this->card('Desconto base',$discount==='—'?'—':$discount.'%').$this->card('C$ Bume',$this->money($wallet['cashbume'])).
            $this->card('Bumes',$this->num($wallet['bumes'])).$this->card('R$',$this->money($wallet['brl'])).'</div>');
    }

    public function partnerGovernance(): string
    {
        $c=$this->partnerContext(); if(!$c['ok']) return $c['message']; $id=$c['post_id'];
        $cnpj=get_post_meta($id,'cnpj_do_parceiro',true) ?: get_user_meta(get_current_user_id(),'serben_partner_cnpj',true);
        $reason=get_post_meta($id,'razao_social',true) ?: get_the_title($id);
        $base=get_post_meta($id,'desconto_base_vitrine',true) ?: get_post_meta($id,'cashback_geral',true);
        $editable=['whatsapp'=>'Telefone / WhatsApp comercial','endereco'=>'Endereço','numero'=>'Número','complemento'=>'Complemento','google_maps'=>'Link / rota Google Maps','horarios_atendimento'=>'Horários de atendimento','instagram'=>'Instagram','facebook'=>'Facebook','site'=>'Website'];
        $h='<h3>Governança e cadastro</h3><div class="serben-dashboard-readonly">'.$this->row('CNPJ / CPF',$this->formatDoc((string)$cnpj),'Somente leitura').$this->row('Razão Social',(string)$reason,'Somente leitura').$this->row('Desconto Base de Vitrine',$base!==''?$base.'%':'—','Somente leitura').'</div>';
        $h.='<form class="serben-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="serben_bume_partner_governance_save"><input type="hidden" name="post_id" value="'.$id.'">'.wp_nonce_field('serben_bume_partner_governance','nonce',true,false);
        foreach($editable as $k=>$label){$v=get_post_meta($id,$k,true);$h.='<p><label><strong>'.esc_html($label).'</strong><br><input type="text" name="fields['.esc_attr($k).']" value="'.esc_attr((string)$v).'" class="widefat"></label></p>';}
        $h.='<p><button class="serben-button" type="submit">Salvar dados liberados</button></p></form><p><small>Logotipo oficial: novo envio deve passar por moderação. Alterações jurídicas e do desconto base exigem solicitação administrativa.</small></p>';
        return $this->wrap('serben-bume-governance',$h);
    }

    public function partnerCoupons(): string
    {
        $c=$this->partnerContext(); if(!$c['ok']) return $c['message']; $plan=$this->partnerPlan(); $limit=$this->couponLimit($plan);
        if($limit===0) return $this->wrap('serben-bume-coupons','<h3>Cupons</h3><div class="serben-message">Criação de cupons indisponível no plano Bume Start.</div>');
        $q=new \WP_Query(['post_type'=>'serben_bume_cupom','post_status'=>['publish','draft'],'author'=>get_current_user_id(),'posts_per_page'=>50]);
        $active=0;$list=''; foreach($q->posts as $p){if($p->post_status==='publish')$active++;$list.='<tr><td>'.esc_html($p->post_title).'</td><td>'.esc_html((string)get_post_meta($p->ID,'serben_coupon_value',true)).'</td><td>'.esc_html((string)get_post_meta($p->ID,'serben_coupon_valid_until',true)).'</td><td>'.($p->post_status==='publish'?'Ativo':'Rascunho').'</td></tr>';}
        $can=$limit<0||$active<$limit; $h='<h3>Cupons e agenda</h3><p>Ativos: <strong>'.$active.'</strong> / '.($limit<0?'ilimitados':$limit).'</p><table class="serben-dashboard-table"><thead><tr><th>Título</th><th>Desconto</th><th>Validade</th><th>Status</th></tr></thead><tbody>'.($list?:'<tr><td colspan="4">Nenhum cupom cadastrado.</td></tr>').'</tbody></table>';
        if($can){$h.='<form class="serben-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="serben_bume_coupon_save">'.wp_nonce_field('serben_bume_coupon','nonce',true,false).'<p><label>Título<br><input required name="title" class="widefat"></label></p><p><label>Descrição<br><textarea name="description" class="widefat"></textarea></label></p><p><label>Desconto promocional<br><input required name="value" placeholder="Ex.: 20%" class="widefat"></label></p><p><label>Validade<br><input type="date" name="valid_until"></label></p><p><label>Dias/horários de ativação<br><input name="schedule" placeholder="Ex.: Ter-Qui, 14h-18h" class="widefat"></label></p><p><label>Estoque limite<br><input type="number" min="0" name="stock"></label></p><button class="serben-button">Criar cupom</button></form>';}
        return $this->wrap('serben-bume-coupons',$h);
    }

    public function partnerWallet(): string
    {
        $c=$this->partnerContext(); if(!$c['ok']) return $c['message']; $w=$this->partnerWalletData();
        return $this->wrap('serben-bume-wallet','<h3>Multicarteira</h3><div class="serben-component-grid">'.$this->card('Saldo R$',$this->money($w['brl'])).$this->card('C$ Bume',$this->money($w['cashbume'])).$this->card('Bumes',$this->num($w['bumes'])).'</div><p><small>Saldos são somente leitura e devem variar por transações, recargas e créditos autorizados.</small></p>');
    }

    public function partnerPackages(): string
    {
        $c=$this->partnerContext(); if(!$c['ok']) return $c['message'];
        $rows=[['Bronze (C$)','C$ 50,00','R$ 54,90','R$ 62,90'],['Prata (C$)','C$ 100,00','R$ 107,90','R$ 119,90'],['Ouro (C$)','C$ 250,00','R$ 266,90','R$ 294,90'],['Turbo (C$)','C$ 500,00','R$ 531,90','R$ 584,90'],['Lote 50 Bumes','50 Bumes','R$ 60,90','R$ 69,90'],['Lote 100 Bumes','100 Bumes','R$ 119,90','R$ 133,90'],['Lote 250 Bumes','250 Bumes','R$ 294,90','R$ 329,90'],['Lote 500 Bumes','500 Bumes','R$ 589,90','R$ 649,90']];
        $body='';foreach($rows as $r){$body.='<tr><td>'.esc_html($r[0]).'</td><td>'.$r[1].'</td><td>'.$r[2].'</td><td>'.$r[3].'</td></tr>';}
        return $this->wrap('serben-bume-packages','<h3>Pacotes de recarga</h3><table class="serben-dashboard-table"><thead><tr><th>Pacote</th><th>Saldo entregue</th><th>Pix/Boleto</th><th>Cartão</th></tr></thead><tbody>'.$body.'</tbody></table><p><small>Este bloco apresenta a vitrine de recargas. A cobrança/creditamento deve ser conectado ao gateway e à rotina transacional antes de habilitar compra em produção.</small></p>');
    }

    public function partnerAgencyHub(): string
    {
        $c=$this->partnerContext(); if(!$c['ok']) return $c['message'];$plan=$this->partnerPlan();$disc=stripos($plan,'pro')!==false?30:(stripos($plan,'impulso')!==false?20:0);
        $services=apply_filters('serben_bume_agency_services',['Design de Banners Profissionais','Gestão de Redes Sociais','Cardápios Digitais','Criação de Sites e Landing Pages']);
        $h='<h3>Hub da Agência Parceira</h3><p>Desconto do seu plano: <strong>'.$disc.'%</strong></p><div class="serben-component-grid">';foreach($services as $s){$h.=$this->card((string)$s,$disc?'Desconto de '.$disc.'%':'Tabela comercial');}$h.='</div>';
        return $this->wrap('serben-bume-agency-hub',$h);
    }

    public function entityOverview(): string
    {
        if(!$this->isEntity())return $this->entityLoginMessage();$u=get_current_user_id();$plan=(string)(get_user_meta($u,'serben_entity_plan',true)?:'Entidade Start');$lives=(int)get_user_meta($u,'serben_entity_lives',true);$fee=$this->entityFee($plan,$lives);
        return $this->wrap('serben-entity-overview','<h2>'.esc_html((string)(get_user_meta($u,'serben_entity_name',true)?:wp_get_current_user()->display_name)).'</h2><div class="serben-component-grid">'.$this->card('Plano',$plan).$this->card('Vidas declaradas',number_format_i18n($lives)).$this->card('Mensalidade',$this->money($fee)).$this->card('Próximo repasse','Dia 20').'</div>');
    }

    public function entityGovernance(): string
    {
        if(!$this->isEntity())return $this->entityLoginMessage();$u=get_current_user_id();$locked=['serben_entity_name'=>'Razão Social / Nome','serben_entity_cnpj'=>'CNPJ','serben_entity_address'=>'Endereço da sede'];$h='<h3>Governança da Entidade</h3>';foreach($locked as $k=>$l)$h.=$this->row($l,(string)get_user_meta($u,$k,true),'Somente leitura');
        $h.='<form class="serben-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="serben_entity_governance_save">'.wp_nonce_field('serben_entity_governance','nonce',true,false);foreach(['serben_entity_phone'=>'Telefone / WhatsApp','serben_entity_logo_url'=>'URL do Logotipo Oficial','serben_entity_lives'=>'Quantidade de Sócios Declarados'] as $k=>$l){$v=get_user_meta($u,$k,true);$type=$k==='serben_entity_lives'?'number':'text';$h.='<p><label><strong>'.$l.'</strong><br><input class="widefat" type="'.$type.'" name="fields['.$k.']" value="'.esc_attr((string)$v).'"></label></p>';}$h.='<button class="serben-button">Salvar</button></form>';
        return $this->wrap('serben-entity-governance',$h);
    }

    public function entityWallet(): string
    {
        if(!$this->isEntity())return $this->entityLoginMessage();$u=get_current_user_id();$brl=(float)get_user_meta($u,'serben_entity_wallet_brl',true);$net=(float)get_user_meta($u,'serben_entity_wallet_net_bumes',true);
        return $this->wrap('serben-entity-wallet','<h3>Carteira Digital Institucional</h3><div class="serben-component-grid">'.$this->card('Saldo para repasse',$this->money($brl)).$this->card('Net Bumes',$this->num($net)).$this->card('Piso de repasse','R$ 200,00').$this->card('Liquidação','Dia 20').'</div>');
    }

    public function entityStatement(): string
    {
        if(!$this->isEntity())return $this->entityLoginMessage();$rows=apply_filters('serben_entity_statement_rows',[],get_current_user_id());$h='<h3>Extrato institucional</h3><table class="serben-dashboard-table"><thead><tr><th>Data/Hora</th><th>ID da Operação</th><th>Tipo</th><th>Base/Cota</th><th>Valor</th></tr></thead><tbody>';foreach($rows as $r){$h.='<tr><td>'.esc_html($r['date']??'').'</td><td>'.esc_html($r['operation_id']??'').'</td><td>'.esc_html($r['type']??'').'</td><td>'.esc_html($r['base']??'').'</td><td>'.esc_html($r['value']??'').'</td></tr>';}$h.= $rows?'':'<tr><td colspan="5">Nenhuma movimentação disponível.</td></tr>';$h.='</tbody></table><p><small>O extrato não deve expor nomes ou CPFs de associados; use apenas identificadores anonimizados.</small></p>';return $this->wrap('serben-entity-statement',$h);
    }

    public function entityBilling(): string
    {
        if(!$this->isEntity())return $this->entityLoginMessage();$u=get_current_user_id();$invoice=apply_filters('serben_entity_current_invoice',[], $u);$history=apply_filters('serben_entity_invoice_history',[], $u);$h='<h3>Faturas e mensalidades</h3>';if($invoice){$h.='<div class="serben-component-grid">'.$this->card('Valor',$invoice['amount']??'—').$this->card('Vencimento',$invoice['due_date']??'—').$this->card('Status',$invoice['status']??'—').'</div>';if(!empty($invoice['boleto_url']))$h.='<p><a class="serben-button" href="'.esc_url($invoice['boleto_url']).'">Baixar boleto em PDF</a></p>';if(!empty($invoice['pix_copy_paste']))$h.='<p><label>Pix copia e cola<br><input class="widefat" readonly value="'.esc_attr($invoice['pix_copy_paste']).'"></label></p>';}else{$h.='<div class="serben-message">Nenhuma fatura sincronizada. O shortcode está pronto para receber os dados do módulo de cobrança.</div>';}$h.='<h4>Histórico</h4><table class="serben-dashboard-table"><thead><tr><th>Competência</th><th>Valor</th><th>Status</th></tr></thead><tbody>';foreach(array_slice($history,0,12) as $r)$h.='<tr><td>'.esc_html($r['period']??'').'</td><td>'.esc_html($r['amount']??'').'</td><td>'.esc_html($r['status']??'').'</td></tr>';$h.=$history?'':'<tr><td colspan="3">Sem histórico sincronizado.</td></tr>';$h.='</tbody></table>';return $this->wrap('serben-entity-billing',$h);
    }

    public function entityBenefits(): string
    {
        if(!$this->isEntity())return $this->entityLoginMessage();$u=get_current_user_id();$coupon=(string)(get_user_meta($u,'serben_entity_telemedicine_coupon',true)?:'SINDICATO15');$cert=(string)get_user_meta($u,'serben_entity_certificate_url',true);$h='<h3>Vantagens Institucionais</h3><div class="serben-component-grid">'.$this->card('Telemedicina','15% de desconto','Cupom: '.esc_html($coupon)).$this->card('Certificado Digital','Preço promocional',$cert?'<a href="'.esc_url($cert).'">Emitir com desconto institucional</a>':'Link aguardando configuração').'</div>';return $this->wrap('serben-entity-benefits',$h);
    }

    public function entityPricing(): string
    {
        $rows=[['Até 1.000','Micro Entidade / Subsedes','R$ 290,00','R$ 860,00'],['1.001 a 3.000','Pequeno Porte','R$ 390,00','R$ 980,00'],['3.001 a 5.000','Médio Porte','R$ 520,00','R$ 1.150,00'],['5.001 a 10.000','Grande Porte','R$ 650,00','R$ 1.320,00'],['Acima de 10.000','Federações / Centrais','R$ 790,00','R$ 1.490,00']];$b='';foreach($rows as $r)$b.='<tr><td>'.$r[0].'</td><td>'.$r[1].'</td><td>'.$r[2].'</td><td>'.$r[3].'</td></tr>';return $this->wrap('serben-entity-pricing','<h3>Planos para Entidades Emissoras</h3><table class="serben-dashboard-table"><thead><tr><th>Faixa de associados</th><th>Porte</th><th>Entidade Start</th><th>Entidade PRO Mais</th></tr></thead><tbody>'.$b.'</tbody></table>');
    }

    public function savePartnerGovernance(): void
    {
        $c=$this->partnerContext();if(!$c['ok']||!isset($_POST['nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])),'serben_bume_partner_governance'))wp_die('Acesso negado.');$id=absint($_POST['post_id']??0);if($id!==$c['post_id'])wp_die('Parceiro inválido.');$allowed=['whatsapp','endereco','numero','complemento','google_maps','horarios_atendimento','instagram','facebook','site'];$f=is_array($_POST['fields']??null)?wp_unslash($_POST['fields']):[];foreach($allowed as $k)if(array_key_exists($k,$f))update_post_meta($id,$k,$k==='site'||$k==='google_maps'?esc_url_raw($f[$k]):sanitize_text_field($f[$k]));$this->back('serben_saved');
    }

    public function saveEntityGovernance(): void
    {
        if(!$this->isEntity()||!isset($_POST['nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])),'serben_entity_governance'))wp_die('Acesso negado.');$f=is_array($_POST['fields']??null)?wp_unslash($_POST['fields']):[];$u=get_current_user_id();if(isset($f['serben_entity_phone']))update_user_meta($u,'serben_entity_phone',sanitize_text_field($f['serben_entity_phone']));if(isset($f['serben_entity_logo_url']))update_user_meta($u,'serben_entity_logo_url',esc_url_raw($f['serben_entity_logo_url']));if(isset($f['serben_entity_lives']))update_user_meta($u,'serben_entity_lives',max(0,absint($f['serben_entity_lives'])));$this->back('serben_saved');
    }

    public function saveCoupon(): void
    {
        $c=$this->partnerContext();if(!$c['ok']||!isset($_POST['nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])),'serben_bume_coupon'))wp_die('Acesso negado.');$limit=$this->couponLimit($this->partnerPlan());if($limit===0)wp_die('Seu plano não permite cupons.');$q=new \WP_Query(['post_type'=>'serben_bume_cupom','post_status'=>'publish','author'=>get_current_user_id(),'posts_per_page'=>-1,'fields'=>'ids']);if($limit>0&&$q->found_posts>=$limit)wp_die('Limite de cupons ativos atingido.');$id=wp_insert_post(['post_type'=>'serben_bume_cupom','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>sanitize_text_field(wp_unslash($_POST['title']??'')),'post_content'=>sanitize_textarea_field(wp_unslash($_POST['description']??''))]);if(is_wp_error($id))wp_die('Não foi possível criar o cupom.');foreach(['value','valid_until','schedule','stock'] as $k)update_post_meta($id,'serben_coupon_'.$k,sanitize_text_field(wp_unslash($_POST[$k]??'')));update_post_meta($id,'serben_partner_post_id',$c['post_id']);$this->back('serben_coupon_created');
    }

    private function partnerContext(): array { $u=wp_get_current_user();if(!$u->exists()||(!in_array('serben_lojista',(array)$u->roles,true)&&!current_user_can('manage_options')))return ['ok'=>false,'post_id'=>0,'message'=>'<div class="serben-message">Faça login como parceiro para visualizar.</div>'];$id=(int)get_user_meta($u->ID,'serben_partner_post_id',true);if(!$id)return ['ok'=>false,'post_id'=>0,'message'=>'<div class="serben-message serben-error">Usuário sem parceiro vinculado.</div>'];return ['ok'=>true,'post_id'=>$id,'message'=>'']; }
    private function isEntity(): bool { $u=wp_get_current_user();return $u->exists()&&(in_array('serben_entidade',(array)$u->roles,true)||current_user_can('manage_options')); }
    private function entityLoginMessage(): string{return '<div class="serben-message">Faça login como Entidade Emissora para visualizar.</div>';}
    private function partnerPlan(): string{return (string)(get_user_meta(get_current_user_id(),'serben_partner_plan_name',true)?:'Bume Start');}
    private function couponLimit(string $p): int {if(stripos($p,'pro')!==false||stripos($p,'crescimento')!==false)return -1;if(stripos($p,'impulso')!==false)return 2;return 0;}
    private function partnerWalletData(): array{$u=get_current_user_id();return ['brl'=>(float)get_user_meta($u,'serben_partner_wallet_brl',true),'cashbume'=>(float)get_user_meta($u,'serben_partner_wallet_cashbume',true),'bumes'=>(float)get_user_meta($u,'serben_partner_wallet_bumes',true)];}
    private function entityFee(string $plan,int $lives): float{$pro=stripos($plan,'pro')!==false;$i=$lives<=1000?0:($lives<=3000?1:($lives<=5000?2:($lives<=10000?3:4)));$a=$pro?[860,980,1150,1320,1490]:[290,390,520,650,790];return $a[$i];}
    private function wrap(string $class,string $html): string{return '<div class="serben-dashboard-module '.esc_attr($class).'">'.$html.'</div>';}
    private function card(string $label,string $value,string $desc=''): string{return '<div class="serben-component-card"><span class="serben-component-label">'.esc_html($label).'</span><strong class="serben-component-value">'.esc_html($value?:'—').'</strong>'.($desc?'<small>'.$desc.'</small>':'').'</div>';}
    private function row(string $l,string $v,string $badge): string{return '<div class="serben-dashboard-row"><strong>'.esc_html($l).'</strong><span>'.esc_html($v?:'—').'</span><small>'.esc_html($badge).'</small></div>';}
    private function money($v): string{return 'R$ '.number_format((float)$v,2,',','.');}private function num($v): string{return number_format((float)$v,2,',','.');}
    private function formatDoc(string $v): string{$d=preg_replace('/\D+/','',$v);if(strlen($d)===14)return substr($d,0,2).'.'.substr($d,2,3).'.'.substr($d,5,3).'/'.substr($d,8,4).'-'.substr($d,12,2);if(strlen($d)===11)return substr($d,0,3).'.'.substr($d,3,3).'.'.substr($d,6,3).'-'.substr($d,9,2);return $v;}
    private function back(string $arg): void{$url=wp_get_referer()?:home_url('/');wp_safe_redirect(add_query_arg($arg,'1',$url));exit;}
}
