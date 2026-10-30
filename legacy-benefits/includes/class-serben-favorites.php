<?php
/** Favoritos de parceiros/ofertas. */
defined( 'ABSPATH' ) || exit;
final class Serben_Favorites {
	const META_KEY = '_serben_favoritos';
	public function init(): void {
		add_action( 'wp_ajax_serben_toggle_favorite', array( $this, 'toggle' ) );
		add_action( 'wp_ajax_nopriv_serben_toggle_favorite', array( $this, 'login_required' ) );
		add_shortcode( 'serben_favoritos', array( $this, 'favorites_shortcode' ) );
		add_shortcode( 'serben_favorito_botao', array( $this, 'button_shortcode' ) );
	}
	public static function ids( int $user_id=0 ): array {
		$user_id=$user_id?:get_current_user_id(); if(!$user_id)return array();
		$ids=get_user_meta($user_id,self::META_KEY,true); if(!is_array($ids))$ids=array();
		return array_values(array_unique(array_filter(array_map('absint',$ids))));
	}
	public static function is_favorite( int $post_id, int $user_id=0 ): bool { return in_array($post_id,self::ids($user_id),true); }
	public static function button( int $post_id, string $label='Favoritar' ): string {
		if(!$post_id||SERBEN_POST_TYPE!==get_post_type($post_id))return '';
		$active=is_user_logged_in()&&self::is_favorite($post_id);$text=$active?'Favoritado':$label;
		$login=is_user_logged_in()?'':wp_login_url(get_permalink($post_id));
		return '<button type="button" class="serben-favorite-button'.($active?' is-active':'').'" data-serben-favorite data-post-id="'.esc_attr($post_id).'" data-login-url="'.esc_url($login).'" aria-pressed="'.($active?'true':'false').'" aria-label="'.esc_attr($text).'"><span aria-hidden="true">'.Serben_Utils::fa_icon('heart','serben-favorite-icon').'</span><em>'.esc_html($text).'</em></button>';
	}
	public function button_shortcode( array $atts=array() ): string { $a=shortcode_atts(array('id'=>0,'texto'=>'Favoritar'),$atts,'serben_favorito_botao'); return self::button(absint($a['id'])?:get_queried_object_id(),sanitize_text_field($a['texto'])); }
	public function login_required(): void { wp_send_json_error(array('message'=>'Entre na sua conta para salvar favoritos.','login_url'=>wp_login_url(wp_get_referer()?:home_url('/'))),401); }
	public function toggle(): void {
		check_ajax_referer('serben_public_nonce','nonce'); if(!is_user_logged_in())$this->login_required();
		$id=absint($_POST['post_id']??0);if(!$id||SERBEN_POST_TYPE!==get_post_type($id)||'publish'!==get_post_status($id))wp_send_json_error(array('message'=>'Oferta inválida.'),400);
		$ids=self::ids();$active=in_array($id,$ids,true);if($active)$ids=array_values(array_diff($ids,array($id)));else$ids[]=$id;
		update_user_meta(get_current_user_id(),self::META_KEY,array_values(array_unique($ids)));
		wp_send_json_success(array('active'=>!$active,'label'=>!$active?'Favoritado':'Favoritar','post_id'=>$id));
	}
	public function favorites_shortcode(): string {
		wp_enqueue_style('serben-public');wp_enqueue_script('serben-public');
		if(!is_user_logged_in())return '<section class="serben-favorites-page"><div class="serben-favorites-empty"><span class="serben-kicker">SEUS FAVORITOS</span><h2>Entre para ver suas ofertas favoritas</h2><p>Salve parceiros e benefícios para encontrar tudo mais rápido depois.</p><a class="serben-primary-link" href="'.esc_url(wp_login_url(get_permalink())).'">Entrar na minha conta</a></div></section>';
		$ids=self::ids();$html='<section class="serben-favorites-page"><div class="serben-favorites-heading"><span class="serben-kicker">SEUS FAVORITOS</span><h2><strong>Benefícios que você salvou</strong> para aproveitar depois</h2><p>Seus parceiros favoritos ficam reunidos aqui.</p></div>';
		if(!$ids)return $html.'<div class="serben-favorites-empty"><h3>Nenhum favorito ainda</h3><p>Abra uma oferta e toque em “Favoritar”.</p><a class="serben-primary-link" href="'.esc_url(home_url('/credenciados/')).'">Explorar ofertas</a></div></section>';
		$html.='<div class="serben-favorites-grid">';foreach($ids as $id){if('publish'===get_post_status($id))$html.=Serben_Shortcodes::render_card($id);}return $html.'</div></section>';
	}
}
