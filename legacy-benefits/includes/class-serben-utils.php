<?php
/**
 * Shared utilities.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Utils {
	public static function meta_key( string $key ): string {
		return SERBEN_META_PREFIX . sanitize_key( $key );
	}

	public static function clean_text( string $value ): string {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = str_replace( array( "\xC2\xA0", "\u{00A0}" ), ' ', $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		return trim( (string) $value );
	}

	public static function normalize_string( string $value ): string {
		$value = remove_accents( wp_strip_all_tags( self::clean_text( $value ) ) );
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/i', ' ', $value );
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
		return $value;
	}

	public static function normalize_slug( string $value ): string {
		$value = remove_accents( wp_strip_all_tags( self::clean_text( $value ) ) );
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/i', '-', $value );
		return trim( (string) $value, '-' );
	}

	public static function split_terms( string $value ): array {
		$value = str_replace( array( '|', ',', '/' ), ';', $value );
		$parts = array_filter( array_map( 'trim', explode( ';', $value ) ) );
		return array_values( array_unique( $parts ) );
	}

	public static function sanitize_url_or_empty( $value ): string {
		$value = self::clean_text( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( ! preg_match( '#^https?://#i', $value ) && preg_match( '#^[a-z0-9.-]+\.[a-z]{2,}#i', $value ) ) {
			$value = 'https://' . $value;
		}
		return esc_url_raw( $value );
	}

	public static function sanitize_phone( $value ): string {
		$value = self::clean_text( (string) $value );
		return trim( (string) preg_replace( '/[^0-9+()\-\s.]/u', '', $value ) );
	}

	public static function whatsapp_link( string $phone, string $message = '' ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		if ( '' === $digits ) {
			return '';
		}
		if ( strlen( $digits ) <= 11 ) {
			$digits = '55' . $digits;
		}
		$url = 'https://wa.me/' . $digits;
		if ( '' !== $message ) {
			$url .= '?text=' . rawurlencode( $message );
		}
		return esc_url( $url );
	}

	public static function get_client_ip(): string {
		$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );
		foreach ( $keys as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			if ( false !== strpos( $value, ',' ) ) {
				$value = trim( explode( ',', $value )[0] );
			}
			return $value;
		}
		return '0.0.0.0';
	}

	public static function allowed_embed_html(): array {
		return array(
			'iframe' => array(
				'src'             => true,
				'width'           => true,
				'height'          => true,
				'style'           => true,
				'allowfullscreen' => true,
				'loading'         => true,
				'referrerpolicy'  => true,
			),
			'a'      => array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
			),
			'br'     => array(),
			'strong' => array(),
			'em'     => array(),
			'img'    => array(
				'src'    => true,
				'alt'    => true,
				'border' => true,
				'width'  => true,
				'height' => true,
			),
			'p'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
		);
	}


	/**
	 * Ícones SVG embutidos no próprio plugin. Não dependem de Font Awesome,
	 * CDN, webfont, Elementor ou cache externo.
	 */
	public static function icon_svg( string $name, string $class = 'serben-icon' ): string {
		$icons = array(
			'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
			'grid' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
			'tag' => '<path d="M4 5h7l9 9-6 6-9-9V5Z"/><circle cx="8" cy="8" r="1.2"/>',
			'arrow-right' => '<path d="M5 12h14M14 7l5 5-5 5"/>',
			'star' => '<path d="m12 3 2.6 5.3 5.9.9-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9-4.3-4.2 5.9-.9L12 3Z"/>',
			'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.4 2.6 3.7 5.6 3.7 9S14.4 18.4 12 21M12 3C9.6 5.6 8.3 8.6 8.3 12s1.3 6.4 3.7 9"/>',
			'pin' => '<path d="M12 21s6-5.3 6-11a6 6 0 1 0-12 0c0 5.7 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/>',
			'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
			'ticket' => '<path d="M4 7h16v3a2 2 0 0 0 0 4v3H4v-3a2 2 0 0 0 0-4V7Z"/><path d="M12 8.5v7" stroke-dasharray="2 2"/>',
			'coins' => '<ellipse cx="10" cy="7" rx="5" ry="2.5"/><path d="M5 7v4c0 1.4 2.2 2.5 5 2.5s5-1.1 5-2.5V7M5 11v4c0 1.4 2.2 2.5 5 2.5 1.2 0 2.3-.2 3.2-.6"/><path d="M15 12c2.5 0 4.5 1 4.5 2.3v3.2c0 1.3-2 2.3-4.5 2.3s-4.5-1-4.5-2.3"/>',
			'share' => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/>',
			'external' => '<path d="M13 5h6v6M19 5l-8 8"/><path d="M18 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h4"/>',
			'cart' => '<path d="M4 5h2l1.3 8.2a2 2 0 0 0 2 1.7H17a2 2 0 0 0 1.9-1.4L20 9H7"/><circle cx="10" cy="18" r="1.3"/><circle cx="17" cy="18" r="1.3"/>',
			'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
			'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.6 2.6L16.5 9"/>',
			'list-check' => '<path d="M9 7h10M9 12h10M9 17h10"/><path d="m4.5 7 1 1 2-2M4.5 12l1 1 2-2M4.5 17l1 1 2-2"/>',
			'id-card' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M6 15c.8-1.6 1.8-2.4 3-2.4s2.2.8 3 2.4M14 9h4M14 12h4"/>',
			'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/>',
			'shield' => '<path d="M12 3 19 6v5c0 4.6-2.8 7.9-7 10-4.2-2.1-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/>',
			'home' => '<path d="m4 11 8-7 8 7v9h-6v-6h-4v6H4v-9Z"/>',
			'phone' => '<path d="M6.5 4h3l1.2 4-2 1.3c1.1 2.5 3 4.4 5.5 5.5l1.3-2 4 1.2v3c0 1.1-.9 2-2 2C10 19 5 14 5 6a2 2 0 0 1 1.5-2Z"/>',
			'whatsapp' => '<path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4A8 8 0 1 1 20 11.5Z"/><path d="M9 8.5c.5 2.3 2.2 4 4.5 4.8"/>',
			'instagram' => '<rect x="4" y="4" width="16" height="16" rx="5"/><circle cx="12" cy="12" r="3.5"/><circle cx="17" cy="7" r=".7" fill="currentColor" stroke="none"/>',
			'facebook' => '<path d="M13.5 20v-7h2.5l.5-3h-3V8.5c0-.9.4-1.5 1.7-1.5H17V4.3c-.6-.1-1.4-.3-2.5-.3-2.6 0-4.4 1.6-4.4 4.5V10H8v3h2.1v7h3.4Z"/>',
			'youtube' => '<rect x="3" y="6" width="18" height="12" rx="4"/><path d="m10 9 5 3-5 3V9Z"/>',
			'tiktok' => '<path d="M14 4v10.2a4.3 4.3 0 1 1-3.6-4.2v3a1.4 1.4 0 1 0 .7 1.2V4h2.9c.5 2 1.7 3.3 4 3.8v2.8c-1.7-.2-3-.8-4-1.7"/>',
			'dumbbell' => '<path d="M5 9v6M8 7v10M16 7v10M19 9v6M8 12h8M3 10v4M21 10v4"/>',
			'shirt' => '<path d="m8 5 4 2 4-2 4 3-3 4v8H7v-8L4 8l4-3Z"/>',
			'plane' => '<path d="m3 11 18-7-7 18-2-8-9-3Z"/><path d="m12 14 4-4"/>',
			'utensils' => '<path d="M7 3v8M4 3v5c0 2 1 3 3 3s3-1 3-3V3M7 11v10M16 3v18M16 3c3 2 4 5 4 8h-4"/>',
			'heart' => '<path d="M20 8c0 5-8 11-8 11S4 13 4 8a4 4 0 0 1 7-2.6A4 4 0 0 1 20 8Z"/>',
			'graduation' => '<path d="m3 9 9-5 9 5-9 5-9-5Z"/><path d="M7 12v4c3 2 7 2 10 0v-4M21 9v6"/>',
			'briefcase' => '<rect x="3" y="7" width="18" height="12" rx="2"/><path d="M9 7V5h6v2M3 12h18M10 12v2h4v-2"/>',
			'laptop' => '<rect x="5" y="5" width="14" height="10" rx="1"/><path d="M3 18h18"/>',
			'baby' => '<circle cx="12" cy="8" r="3"/><path d="M8 20v-5c0-2 1.8-3 4-3s4 1 4 3v5M9 5c1-2 5-2 6 0"/>',
			'file' => '<path d="M6 3h8l4 4v14H6V3Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
			'car' => '<path d="M5 16h14l-1-6-2-3H8L6 10l-1 6Z"/><circle cx="8" cy="17" r="1.5"/><circle cx="16" cy="17" r="1.5"/><path d="M7 11h10"/>',
			'cart2' => '<path d="M4 5h2l2 10h9l2-7H7M9 19h.01M17 19h.01"/>',
			'sparkles' => '<path d="m12 3 1.1 3.1L16 7.2l-2.9 1.1L12 12l-1.1-3.7L8 7.2l2.9-1.1L12 3ZM5 13l.8 2.2L8 16l-2.2.8L5 19l-.8-2.2L2 16l2.2-.8L5 13ZM18 12l.8 2.2L21 15l-2.2.8L18 18l-.8-2.2L15 15l2.2-.8L18 12Z"/>',
			'pencil' => '<path d="m5 19 4-1 9-9-3-3-9 9-1 4Z"/><path d="m13 8 3 3"/>',
			'drink' => '<path d="M6 4h12l-2 16H8L6 4Z"/><path d="M7 8h10M15 4l3-2"/>',
			'bag' => '<path d="M5 8h14l1 12H4L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
			'flower' => '<circle cx="12" cy="12" r="2"/><path d="M12 10c-4-4-5-7-2-8 2-.7 3 2 2 8ZM14 12c4-4 7-5 8-2 .7 2-2 3-8 2ZM12 14c4 4 5 7 2 8-2 .7-3-2-2-8ZM10 12c-4 4-7 5-8 2-.7-2 2-3 8-2Z"/>',
			'paw' => '<circle cx="8" cy="8" r="2"/><circle cx="16" cy="8" r="2"/><circle cx="5" cy="13" r="1.7"/><circle cx="19" cy="13" r="1.7"/><path d="M8 18c1.2-3 2.5-4 4-4s2.8 1 4 4c-2 1.7-6 1.7-8 0Z"/>',
			'book' => '<path d="M4 5h6c1.2 0 2 .8 2 2v12c0-1.2-.8-2-2-2H4V5ZM20 5h-6c-1.2 0-2 .8-2 2v12c0-1.2.8-2 2-2h6V5Z"/>',
			'gem' => '<path d="M4 8 8 3h8l4 5-8 13L4 8Z"/><path d="M4 8h16M8 3l4 5 4-5"/>',
			'eye' => '<path d="M3 12s3-5 9-5 9 5 9 5-3 5-9 5-9-5-9-5Z"/><circle cx="12" cy="12" r="2.5"/>',
			'store' => '<path d="M4 10h16v10H4V10Z"/><path d="m3 10 2-6h14l2 6M8 20v-6h5v6"/>',
		);

		$paths = $icons[ $name ] ?? $icons['tag'];
		return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
	}


	/**
	 * Ícones Font Awesome Free renderizados via SVG local usando CSS mask.
	 * Isso mantém a biblioteca visual Font Awesome sem depender de webfont,
	 * CDN, Elementor ou carregamento externo no navegador.
	 */
	public static function fa_icon( string $name, string $class = '' ): string {
		$map = array(
			'search' => array( 'solid', 'magnifying-glass' ),
			'close' => array( 'solid', 'xmark' ),
			'grid' => array( 'solid', 'grip' ),
			'tag' => array( 'solid', 'tag' ),
			'arrow-right' => array( 'solid', 'arrow-right' ),
			'chevron-right' => array( 'solid', 'chevron-right' ),
			'chevron-left' => array( 'solid', 'chevron-left' ),
			'star' => array( 'solid', 'star' ),
			'globe' => array( 'solid', 'globe' ),
			'pin' => array( 'solid', 'location-dot' ),
			'calendar' => array( 'solid', 'calendar-days' ),
			'ticket' => array( 'solid', 'ticket' ),
			'coins' => array( 'solid', 'coins' ),
			'share' => array( 'solid', 'share-nodes' ),
			'external' => array( 'solid', 'arrow-up-right-from-square' ),
			'cart' => array( 'solid', 'cart-shopping' ),
			'clock' => array( 'solid', 'clock' ),
			'check-circle' => array( 'solid', 'circle-check' ),
			'list-check' => array( 'solid', 'list-check' ),
			'id-card' => array( 'solid', 'id-card' ),
			'info' => array( 'solid', 'circle-info' ),
			'shield' => array( 'solid', 'shield-halved' ),
			'shield-heart' => array( 'solid', 'shield-heart' ),
			'home' => array( 'solid', 'house' ),
			'house-chimney' => array( 'solid', 'house-chimney' ),
			'phone' => array( 'solid', 'phone' ),
			'whatsapp' => array( 'brands', 'whatsapp' ),
			'instagram' => array( 'brands', 'instagram' ),
			'facebook' => array( 'brands', 'facebook-f' ),
			'youtube' => array( 'brands', 'youtube' ),
			'tiktok' => array( 'brands', 'tiktok' ),
			'dumbbell' => array( 'solid', 'dumbbell' ),
			'shirt' => array( 'solid', 'shirt' ),
			'plane' => array( 'solid', 'plane' ),
			'utensils' => array( 'solid', 'utensils' ),
			'heart' => array( 'solid', 'heart' ),
			'graduation' => array( 'solid', 'graduation-cap' ),
			'briefcase' => array( 'solid', 'briefcase' ),
			'laptop' => array( 'solid', 'laptop' ),
			'baby' => array( 'solid', 'baby' ),
			'file' => array( 'solid', 'file-lines' ),
			'car' => array( 'solid', 'car' ),
			'cart2' => array( 'solid', 'cart-shopping' ),
			'sparkles' => array( 'solid', 'wand-magic-sparkles' ),
			'pencil' => array( 'solid', 'pen' ),
			'drink' => array( 'solid', 'martini-glass' ),
			'bag' => array( 'solid', 'bag-shopping' ),
			'flower' => array( 'solid', 'seedling' ),
			'paw' => array( 'solid', 'paw' ),
			'book' => array( 'solid', 'book' ),
			'gem' => array( 'solid', 'gem' ),
			'eye' => array( 'solid', 'eye' ),
			'store' => array( 'solid', 'store' ),
			'video' => array( 'solid', 'video' ),
			'pills' => array( 'solid', 'pills' ),
			'gifts' => array( 'solid', 'gifts' ),
			'hand-heart' => array( 'solid', 'hand-holding-heart' ),
		);

		$icon = $map[ $name ] ?? $map['tag'];
		$style = $icon[0];
		$file  = $icon[1];
		$path  = SERBEN_PLUGIN_DIR . 'assets/icons/fontawesome/' . $style . '/' . $file . '.svg';
		$slug  = sanitize_html_class( $name );
		$classes = trim( 'serben-fa-icon serben-fa-icon--' . $slug . ( $class !== '' ? ' ' . $class : '' ) );

		static $cache = array();
		if ( ! isset( $cache[ $path ] ) ) {
			$svg = file_exists( $path ) ? (string) file_get_contents( $path ) : '';
			if ( $svg !== '' ) {
				$svg = preg_replace( '/<!--.*?-->/s', '', $svg );
				$svg = preg_replace( '/<svg\b([^>]*)>/i', '<svg$1 aria-hidden="true" focusable="false">', $svg, 1 );
			}
			$cache[ $path ] = $svg;
		}

		if ( $cache[ $path ] === '' ) {
			return '<span class="' . esc_attr( $classes ) . '" aria-hidden="true">●</span>';
		}

		return '<span class="' . esc_attr( $classes ) . '" aria-hidden="true">' . $cache[ $path ] . '</span>';
	}

	/**
	 * Benefícios institucionais exibidos nas páginas individuais.
	 * Os valores podem ser ajustados em Parceiros > Benefícios do Clube.
	 */
	public static function club_benefits(): array {
		$defaults = array(
			'enabled' => '1',
			'eyebrow' => 'Benefícios do Club Serben',
			'title' => 'Mais proteção, cuidado e vantagens para você',
			'intro' => 'Além das ofertas dos parceiros, o Club Serben reúne serviços pensados para acompanhar você no dia a dia.',
			'insurance_title' => 'Seguro por morte acidental e invalidez permanente por acidente',
			'insurance_value' => 'R$ 15.000,00',
			'telemedicine_title' => 'Telemedicina',
			'medicines_title' => 'Medicamentos genéricos',
			'home_assistance_title' => 'Assistência residencial',
			'club_title' => 'Clube de vantagens',
		);
		$saved = get_option( 'serben_club_benefits', array() );
		if ( ! is_array( $saved ) ) $saved = array();
		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Texto curto e útil do benefício para cards e página individual.
	 * Evita o genérico "Benefício online" quando há uma oferta cadastrada.
	 */
	public static function benefit_text( int $post_id, bool $online ): string {
		$awin_enabled = in_array( strtolower( trim( (string) get_post_meta( $post_id, 'awin_cashback_enabled', true ) ) ), array( '1', 'true', 'yes', 'on' ), true );
		$awin_pct = (float) str_replace( ',', '.', (string) get_post_meta( $post_id, 'awin_cashback_percent', true ) );
		if ( $online && $awin_pct > 0 ) {
			$pct = rtrim( rtrim( number_format( $awin_pct, 2, ',', '' ), '0' ), ',' );
			return 'Até ' . $pct . '% de CashBume';
		}

		$keys = $online
			? array( SERBEN_META_PREFIX . 'desconto_online', 'desconto_online', 'cash_bume', 'cashback', 'percentual_cash_bume', SERBEN_META_PREFIX . 'desconto', 'desconto', SERBEN_META_PREFIX . 'titulo_oferta', SERBEN_META_PREFIX . 'descricao_beneficio', 'descricao_beneficio' )
			: array( SERBEN_META_PREFIX . 'desconto', 'desconto', SERBEN_META_PREFIX . 'titulo_oferta', SERBEN_META_PREFIX . 'descricao_beneficio', 'descricao_beneficio' );

		foreach ( $keys as $key ) {
			$value = trim( wp_strip_all_tags( (string) get_post_meta( $post_id, $key, true ) ) );
			if ( $value === '' ) continue;
			$normalized = self::normalize_string( $value );
			if ( in_array( $normalized, array( 'beneficio online', 'oferta online', 'oferta online exclusiva', 'desconto exclusivo', 'vantagem exclusiva', 'vantagem exclusiva para associados' ), true ) ) continue;
			return wp_trim_words( $value, 14, '…' );
		}

		return $online ? 'Vantagem exclusiva na loja online' : 'Vantagem exclusiva para associados';
	}



	/**
	 * Retorna true quando o parceiro deve ser tratado como online.
	 * Centraliza a regra para cards, página individual, REST e importações.
	 */
	public static function partner_is_online( int $post_id ): bool {
		$type = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', true );
		if ( '' === $type ) $type = (string) get_post_meta( $post_id, 'tipo', true );
		$type = strtolower( remove_accents( trim( $type ) ) );
		return (
			false !== strpos( $type, 'online' ) ||
			false !== strpos( $type, 'virtual' ) ||
			false !== strpos( $type, 'ecommerce' ) ||
			false !== strpos( $type, 'e-commerce' ) ||
			absint( get_post_meta( $post_id, 'awin_advertiser_id', true ) ) > 0 ||
			'' !== trim( (string) get_post_meta( $post_id, 'link_afiliado', true ) ) ||
			'' !== trim( (string) get_post_meta( $post_id, 'awin_click_through_url', true ) )
		);
	}

	/**
	 * Logo canônico do parceiro.
	 *
	 * Modos:
	 * - local: usa exclusivamente o attachment escolhido no painel;
	 * - none: remoção intencional, sem ressuscitar URLs antigas/importadas;
	 * - legacy/ausente: compatibilidade com integrações antigas.
	 *
	 * Isso resolve o problema em que um logo removido reaparecia por causa de
	 * awin_logo_url/logo/featured image persistidos por importações antigas.
	 */
	public static function partner_logo_url( int $post_id, string $size = 'large' ): string {
		$mode = sanitize_key( (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_mode', true ) );
		$logo_id = absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', true ) );

		if ( 'none' === $mode ) return '';
		if ( $logo_id ) {
			$url = wp_get_attachment_image_url( $logo_id, $size );
			if ( $url ) return esc_url_raw( $url );
			if ( 'local' === $mode ) return '';
		}
		if ( 'local' === $mode ) return '';

		foreach ( array( 'awin_logo_url', 'logo' ) as $key ) {
			$url = trim( (string) get_post_meta( $post_id, $key, true ) );
			if ( $url && filter_var( $url, FILTER_VALIDATE_URL ) ) return esc_url_raw( $url );
		}

		// Compatibilidade final com instalações antigas que usavam a imagem destacada como logo.
		// Se a própria imagem destacada estiver sendo reconhecida como capa, ela NÃO vira logo.
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$url = wp_get_attachment_image_url( $thumb_id, $size );
			$cover_url = class_exists( 'Serben_Cover_Studio' ) ? Serben_Cover_Studio::public_cover_url( $post_id, $size ) : '';
			if ( $url && ( '' === $cover_url || esc_url_raw( $url ) !== esc_url_raw( $cover_url ) ) ) return esc_url_raw( $url );
		}
		return '';
	}

	public static function partner_logo_id( int $post_id ): int {
		return absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', true ) );
	}

	public static function mark_managed_logo( int $attachment_id, int $post_id, string $source = 'plugin' ): void {
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) return;
		update_post_meta( $attachment_id, '_serben_asset_type', 'logo' );
		update_post_meta( $attachment_id, '_serben_asset_owner', $post_id );
		update_post_meta( $attachment_id, '_serben_asset_source', sanitize_key( $source ) );
	}

	public static function is_managed_logo( int $attachment_id, int $post_id = 0 ): bool {
		if ( 'logo' !== (string) get_post_meta( $attachment_id, '_serben_asset_type', true ) ) return false;
		$owner = absint( get_post_meta( $attachment_id, '_serben_asset_owner', true ) );
		return $post_id > 0 ? $owner === $post_id : $owner > 0;
	}

	/** Exclui somente arquivos que o próprio plugin marcou como logo gerenciado. */
	public static function maybe_delete_managed_logo( int $attachment_id, int $post_id ): bool {
		if ( ! self::is_managed_logo( $attachment_id, $post_id ) ) return false;
		$refs = get_posts( array(
			'post_type' => SERBEN_POST_TYPE,
			'post_status' => 'any',
			'posts_per_page' => 2,
			'fields' => 'ids',
			'meta_key' => SERBEN_META_PREFIX . 'logo_id',
			'meta_value' => $attachment_id,
			'post__not_in' => array( $post_id ),
		) );
		if ( ! empty( $refs ) ) return false;
		return (bool) wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Regra visual: online usa logo como mídia principal. Parceiro físico pode
	 * priorizar capa quando houver uma imagem editorial real.
	 */
	public static function card_media( int $post_id ): array {
		$online = self::partner_is_online( $post_id );
		$logo = self::partner_logo_url( $post_id, 'large' );
		$cover = class_exists( 'Serben_Cover_Studio' ) ? Serben_Cover_Studio::public_cover_url( $post_id, 'large' ) : '';
		$use_cover = ! $online && '' !== $cover;
		return array(
			'online' => $online,
			'logo' => $logo,
			'cover' => $cover,
			'primary' => $use_cover ? $cover : $logo,
			'kind' => $use_cover ? 'cover' : 'logo',
		);
	}

	/**
	 * Perfil visual da marca usado em áreas sem imagem de capa.
	 * Ordem de prioridade: cor manual > detecção automática do logo local >
	 * mapa de marcas conhecidas > neutro. O fallback nunca força o roxo Club Serben.
	 */
	public static function brand_profile( int $post_id, string $name = '' ): array {
		$value = trim( (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'brand_color', true ) );
		if ( ! preg_match( '/^#[0-9A-F]{6}$/i', $value ) ) {
			$value = '#FFFFFF';
		}
		$color = strtoupper( $value );
		return array(
			'color'      => $color,
			'foreground' => self::contrast_color( $color ),
			'source'     => 'manual',
			'filter'     => 'none',
		);
	}

	public static function brand_color( int $post_id, string $name = '' ): string {
		$profile = self::brand_profile( $post_id, $name );
		return (string) $profile['color'];
	}

	public static function contrast_color( string $hex ): string {
		$hex = ltrim( $hex, '#' );
		if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) return '#FFFFFF';
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		$luminance = ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b );
		return $luminance > 168 ? '#1D1920' : '#FFFFFF';
	}

	private static function detect_brand_color_from_attachment( int $attachment_id ): string {
		if ( ! function_exists( 'imagecreatefromstring' ) ) return '';

		$file = get_attached_file( $attachment_id );
		$meta = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $meta ) && ! empty( $meta['sizes']['thumbnail']['file'] ) && $file ) {
			$thumb = trailingslashit( dirname( $file ) ) . $meta['sizes']['thumbnail']['file'];
			if ( is_readable( $thumb ) ) $file = $thumb;
		}
		if ( ! $file || ! is_readable( $file ) ) return '';

		$bytes = @file_get_contents( $file );
		if ( ! $bytes ) return '';
		$image = @imagecreatefromstring( $bytes );
		if ( ! $image ) return '';

		$width  = imagesx( $image );
		$height = imagesy( $image );
		if ( $width < 1 || $height < 1 ) {
			imagedestroy( $image );
			return '';
		}

		$step_x = max( 1, (int) floor( $width / 42 ) );
		$step_y = max( 1, (int) floor( $height / 42 ) );
		$buckets = array();

		for ( $y = 0; $y < $height; $y += $step_y ) {
			for ( $x = 0; $x < $width; $x += $step_x ) {
				$rgba = imagecolorat( $image, $x, $y );
				$a = ( $rgba >> 24 ) & 0x7F;
				if ( $a > 92 ) continue;
				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;

				$max = max( $r, $g, $b );
				$min = min( $r, $g, $b );
				$brightness = ( $r + $g + $b ) / 3;
				$saturation = $max > 0 ? ( $max - $min ) / $max : 0;

				// Ignora fundo branco/quase branco, muito comum em arquivos de logo.
				if ( $brightness > 238 && $saturation < 0.12 ) continue;

				$qr = min( 7, (int) floor( $r / 32 ) );
				$qg = min( 7, (int) floor( $g / 32 ) );
				$qb = min( 7, (int) floor( $b / 32 ) );
				$key = $qr . ':' . $qg . ':' . $qb;
				if ( ! isset( $buckets[ $key ] ) ) $buckets[ $key ] = array( 'score' => 0.0, 'count' => 0, 'r' => 0, 'g' => 0, 'b' => 0 );
				$weight = 1 + ( $saturation * 1.7 );
				$buckets[ $key ]['score'] += $weight;
				$buckets[ $key ]['count']++;
				$buckets[ $key ]['r'] += $r;
				$buckets[ $key ]['g'] += $g;
				$buckets[ $key ]['b'] += $b;
			}
		}
		imagedestroy( $image );

		if ( empty( $buckets ) ) return '';
		uasort( $buckets, static function( $a, $b ) { return $b['score'] <=> $a['score']; } );
		$winner = reset( $buckets );
		if ( empty( $winner['count'] ) ) return '';

		$r = (int) round( $winner['r'] / $winner['count'] );
		$g = (int) round( $winner['g'] / $winner['count'] );
		$b = (int) round( $winner['b'] / $winner['count'] );

		// Evita um cinza claro sem identidade como "cor de marca".
		if ( abs( $r - $g ) < 14 && abs( $g - $b ) < 14 && ( $r + $g + $b ) / 3 > 205 ) return '';
		return sprintf( '#%02X%02X%02X', $r, $g, $b );
	}


	/**
	 * Hierarquia editorial usada na navegação pública do Club Serben.
	 * Mantemos poucas categorias principais e agrupamos os termos legados
	 * como subcategorias/aliases, sem expor a taxonomia histórica na vitrine.
	 */
	public static function market_categories(): array {
		return array(
			array(
				'name' => 'Alimentação & Bebidas', 'slug' => 'alimentacao-bebidas', 'icon' => 'utensils',
				'sources' => array('Alimentação','Alimentos e Bebidas','Bebidas'),
				'children' => array(
					array('name'=>'Restaurantes','slug'=>'restaurantes','sources'=>array('Restaurantes','Churrascaria','Comida por Kilo','Cantinas','Rotisserie')),
					array('name'=>'Cafés & Padarias','slug'=>'cafes-padarias','sources'=>array('Cafés','Padarias','Cestas de Café')),
					array('name'=>'Lanches & Delivery','slug'=>'lanches-delivery','sources'=>array('Lanchonetes','Food delivery','Pastelarias','Pizzarias')),
					array('name'=>'Doces & Chocolates','slug'=>'doces-chocolates','sources'=>array('Docerias','Chocolates e Pelúcias','Flores e Chocolate')),
					array('name'=>'Bares & Bebidas','slug'=>'bares-bebidas','sources'=>array('Bares','Cervejas','Vinhos','Champagnes e Espumantes','Destilados e Saquês')),
				),
			),
			array(
				'name' => 'Beleza & Saúde', 'slug' => 'beleza-saude', 'icon' => 'sparkles',
				'sources' => array('Estética & Beleza','Estética e Beleza','Beleza','Saúde','Óticas','Perfumarias'),
				'children' => array(
					array('name'=>'Estética','slug'=>'estetica','sources'=>array('Tratamentos Estéticos','Botox','Carboxiterapia','Peeling','Peeling Químico','Preenchimento','CO2 Fracionado','Limpeza de pele','Drenagem Linfática','Massagem Modeladora')),
					array('name'=>'Cabelos & Unhas','slug'=>'cabelos-unhas','sources'=>array('Cabeleireiros','Cabelo & Companhia','Escova','Hidratação','Manicure','Pedicure')),
					array('name'=>'Cosméticos & Perfumaria','slug'=>'cosmeticos-perfumaria','sources'=>array('Cosméticos','Perfumarias','Maquiagem','Maquiagem Definitiva')),
					array('name'=>'Óticas','slug'=>'oticas','sources'=>array('Óticas','Ótica')),
					array('name'=>'Bem-estar & Terapias','slug'=>'bem-estar-terapias','sources'=>array('Massagens','Massoterapia','Podologia','SPA DAY','Terapia Floral','Banhos Energéticos')),
				),
			),
			array(
				'name' => 'Moda & Acessórios', 'slug' => 'moda-acessorios', 'icon' => 'shirt',
				'sources' => array('Vestuário & Acessórios','Moda','Confecções','Joalheria'),
				'children' => array(
					array('name'=>'Vestuário','slug'=>'vestuario','sources'=>array('Vestuário & Acessórios','Roupas','Moda Jovem','Moda Social','Malharias','Esportivo')),
					array('name'=>'Calçados','slug'=>'calcados','sources'=>array('Calçados')),
					array('name'=>'Acessórios','slug'=>'acessorios','sources'=>array('Acessórios')),
					array('name'=>'Moda Íntima','slug'=>'moda-intima','sources'=>array('Moda Intima Feminina','Moda Intima Masculina','Moda Sensual')),
					array('name'=>'Joias & Relógios','slug'=>'joias-relogios','sources'=>array('Joias','Jóias','Jóias - Alianças','Relógios','Joalheria')),
					array('name'=>'Personalizados','slug'=>'personalizados','sources'=>array('Camisetas personalizadas','Copos personalizados','Sandálias Personalizadas','Sacolas e Bolsas Ecológicas')),
				),
			),
			array(
				'name' => 'Viagens & Turismo', 'slug' => 'viagens-turismo', 'icon' => 'plane',
				'sources' => array('Turismo & Viagens','Viagem','Turismo & Hotelaria','Motel'),
				'children' => array(
					array('name'=>'Hotéis & Pousadas','slug'=>'hoteis-pousadas','sources'=>array('Hotéis e Pousadas','Motel')),
					array('name'=>'Agências & Pacotes','slug'=>'agencias-pacotes','sources'=>array('Agências de Viagens e Turismo','Pacotes de viagens','Turismo & Hotelaria')),
					array('name'=>'Termas & Resorts','slug'=>'termas-resorts','sources'=>array('Termas')),
					array('name'=>'Locação de Veículos','slug'=>'locacao-veiculos','sources'=>array('Locação de Veículos')),
				),
			),
			array(
				'name' => 'Esportes & Fitness', 'slug' => 'esportes-fitness', 'icon' => 'dumbbell',
				'sources' => array('Academias & Esportes','Esporte e Lazer','Esporte & Fitness'),
				'children' => array(
					array('name'=>'Academias','slug'=>'academias','sources'=>array('Academias','Fitness','Fitness Woman','Musculação','Condicionamento e Recondicionamento Físico')),
					array('name'=>'Personal & Treinamento','slug'=>'personal-treinamento','sources'=>array('Personal Trainer','Body systems')),
					array('name'=>'Pilates, Yoga & Dança','slug'=>'pilates-yoga-danca','sources'=>array('Studio de Pilates','Yoga','Dança')),
					array('name'=>'Lutas','slug'=>'lutas','sources'=>array('Lutas','Boxe','Judô','Karatê','Kung Fu','Taekwondo')),
					array('name'=>'Natação & Hidro','slug'=>'natacao-hidro','sources'=>array('Natação','Hidroginástica')),
					array('name'=>'Corrida, Bike & Tênis','slug'=>'corrida-bike-tenis','sources'=>array('Corrida & Triathlon','Bike Indoor','Tênis','SQUASH','Futebol')),
					array('name'=>'Artigos Esportivos','slug'=>'artigos-esportivos','sources'=>array('Artigos Esportivos')),
				),
			),
			array(
				'name' => 'Casa & Decoração', 'slug' => 'casa-decoracao', 'icon' => 'home',
				'sources' => array('Casa & Decoração','Casa e Decoração','Movéis Para Escritório','Móveis Para Escritório'),
				'children' => array(
					array('name'=>'Decoração','slug'=>'decoracao','sources'=>array('Decoração','Artigos para Presentes')),
					array('name'=>'Móveis','slug'=>'moveis','sources'=>array('Movéis Para Escritório','Móveis Para Escritório','Colchões')),
					array('name'=>'Eletrodomésticos','slug'=>'eletrodomesticos','sources'=>array('Eletrodomésticos')),
					array('name'=>'Utilidades Domésticas','slug'=>'utilidades-domesticas','sources'=>array('Utilidade domestica','Casa')),
					array('name'=>'Flores & Presentes','slug'=>'flores-presentes','sources'=>array('Floricultura','Cestas Especiais','Flores e Chocolate')),
				),
			),
			array(
				'name' => 'Eletrônicos & Tecnologia', 'slug' => 'eletronicos-tecnologia', 'icon' => 'laptop',
				'sources' => array('Informática','Eletrônicos e Tecnologia','Eletronicos e Tecnologia'),
				'children' => array(
					array('name'=>'Informática','slug'=>'informatica','sources'=>array('Informática','Cursos de Informática','Consultoria em informática')),
					array('name'=>'Eletrônicos','slug'=>'eletronicos','sources'=>array('Eletrônicos')),
					array('name'=>'Telefonia','slug'=>'telefonia','sources'=>array('Telefonia','Telecomunicações')),
					array('name'=>'Eletroportáteis','slug'=>'eletroportateis','sources'=>array('Eletroportáteis')),
					array('name'=>'Assistência Técnica','slug'=>'assistencia-tecnica','sources'=>array('Manutenção Micros','Reciclagem de Toner e Cartuchos')),
				),
			),
			array(
				'name' => 'Educação & Cursos', 'slug' => 'educacao-cursos', 'icon' => 'graduation',
				'sources' => array('Cursos & Educação','Educação','Livrarias'),
				'children' => array(
					array('name'=>'Idiomas','slug'=>'idiomas','sources'=>array('Idiomas','Cursos de línguas','Língua Portuguesa','Cursos no Exterior')),
					array('name'=>'Faculdade & Pós','slug'=>'faculdade-pos','sources'=>array('Faculdade','Faculdades & Universidades','Universidade','MBA','Pós-Graduação')),
					array('name'=>'Cursos Profissionalizantes','slug'=>'profissionalizantes','sources'=>array('Cursos Profissionalizantes','Cursos Profissionalizantes na Área da Saúde','Cursos Técnicos','Treinamento - Cursos')),
					array('name'=>'Educação Básica','slug'=>'educacao-basica','sources'=>array('Educação Infantil','Ensino Fundamental','Ensino Médio','Pré Escola','Berçário','Jardim Escola','Complemento Escolar','Supletivo')),
					array('name'=>'Preparatórios','slug'=>'preparatorios','sources'=>array('Pré-Vestibular','Cursos Preparatórios Concursos','Cursos Preparatórios OAB')),
					array('name'=>'Livros','slug'=>'livros','sources'=>array('Livrarias','Disque Livros')),
				),
			),
			array(
				'name' => 'Lazer & Cultura', 'slug' => 'lazer-cultura', 'icon' => 'ticket',
				'sources' => array('Lazer & Cultura','Site de Notícias e Entretenimentos','Esotéricos'),
				'children' => array(
					array('name'=>'Cinema & Teatro','slug'=>'cinema-teatro','sources'=>array('Cinema','Teatro','Curso de Teatro')),
					array('name'=>'Parques & Atrações','slug'=>'parques-atracoes','sources'=>array('Parques','Aquário')),
					array('name'=>'Entretenimento','slug'=>'entretenimento','sources'=>array('Notícias e Entretenimento','Eventos Musicais')),
					array('name'=>'Arte & Cultura','slug'=>'arte-cultura','sources'=>array('Curso de Arte','Escola de Música','Fotografia')),
					array('name'=>'Esotéricos','slug'=>'esotericos','sources'=>array('Esoterismo','Tarot')),
				),
			),
			array(
				'name' => 'Infantil & Família', 'slug' => 'infantil-familia', 'icon' => 'baby',
				'sources' => array('Artigos Infantis','Infantil e Brinquedos','Infantil'),
				'children' => array(
					array('name'=>'Brinquedos','slug'=>'brinquedos','sources'=>array('Brinquedos')),
					array('name'=>'Moda Infantil','slug'=>'moda-infantil','sources'=>array('Moda Infantil','Moda Bebê','Vestuário','Bebês e Crianças')),
					array('name'=>'Educação Infantil','slug'=>'educacao-infantil','sources'=>array('Educação Infantil','Berçário','Pré Escola','Jardim Escola')),
					array('name'=>'Festas Infantis','slug'=>'festas-infantis','sources'=>array('Buffet Infantil')),
				),
			),
			array(
				'name' => 'Pets', 'slug' => 'pets', 'icon' => 'paw',
				'sources' => array('Mundo Animal','Pets'),
				'children' => array(
					array('name'=>'Pet Shop','slug'=>'pet-shop','sources'=>array('Pet-Shop','Mundo Animal')),
					array('name'=>'Veterinária','slug'=>'veterinaria','sources'=>array('Clínica Veterinária')),
					array('name'=>'Hospedagem Pet','slug'=>'hospedagem-pet','sources'=>array('Hotelzinho')),
				),
			),
			array(
				'name' => 'Automotivo & Mobilidade', 'slug' => 'automotivo-mobilidade', 'icon' => 'car',
				'sources' => array('Auto & Acessórios','Automotivo','Transportes & Mudanças'),
				'children' => array(
					array('name'=>'Manutenção','slug'=>'manutencao','sources'=>array('Mecânica Geral','Auto Elétrico','Injeção Eletrônica','Suspensão e Freios','Troca de óleo','Lubrificantes','Higienização de Ar Condicionado')),
					array('name'=>'Pneus & Baterias','slug'=>'pneus-baterias','sources'=>array('Pneus','Baterias','Balanceamento')),
					array('name'=>'Autoescola','slug'=>'autoescola','sources'=>array('Auto Escola')),
					array('name'=>'Concessionárias','slug'=>'concessionarias','sources'=>array('Concessionárias')),
					array('name'=>'Locação de Veículos','slug'=>'locacao-veiculos','sources'=>array('Locação de Veículos')),
					array('name'=>'Transporte & Mudanças','slug'=>'transporte-mudancas','sources'=>array('Mudanças','Transportes & Mudanças')),
				),
			),
			array(
				'name' => 'Serviços', 'slug' => 'servicos', 'icon' => 'briefcase',
				'sources' => array('Assessorias e Consultorias','Serviços','Eventos','Casamentos','Gráfica','Clube Alto de Santana'),
				'children' => array(
					array('name'=>'Consultorias','slug'=>'consultorias','sources'=>array('Assessorias e Consultorias','Assessoria em Documentação','Comércio exterior','Consultoria em informática','Consultoria Imobiliária')),
					array('name'=>'Jurídico & Contábil','slug'=>'juridico-contabil','sources'=>array('Jurídica','Contábil','Despachante','Isenções de impostos')),
					array('name'=>'Arquitetura & Imobiliário','slug'=>'arquitetura-imobiliario','sources'=>array('Arquitetura','Consultoria Imobiliária')),
					array('name'=>'Marketing & Digital','slug'=>'marketing-digital','sources'=>array('Marketing','Web Sites','sites','Gráfica')),
					array('name'=>'Eventos & Casamentos','slug'=>'eventos-casamentos','sources'=>array('Eventos','Casamentos','Aluguel de Salões','Buffet','Eventos Corporativos','Eventos Sociais','Dia da Noiva','Dia do Noivo','Fotografia')),
					array('name'=>'Documentação','slug'=>'documentacao','sources'=>array('Assessoria em Documentação','Despachante')),
				),
			),
			array(
				'name' => 'Supermercados & Varejo', 'slug' => 'supermercados-varejo', 'icon' => 'store',
				'sources' => array('Supermercado','Supermercado online','Varejo','Lojas Virtuais','Lojas de Departamentos','Club de Compras Atacadista','Bazar & Papelaria','Presentes','Floricultura'),
				'children' => array(
					array('name'=>'Supermercados','slug'=>'supermercados','sources'=>array('Supermercado','Supermercado online','Supermercado Varejo')),
					array('name'=>'Lojas de Departamento','slug'=>'lojas-departamento','sources'=>array('Lojas de Departamentos')),
					array('name'=>'Lojas Virtuais','slug'=>'lojas-virtuais','sources'=>array('Lojas Virtuais','Varejo')),
					array('name'=>'Atacado','slug'=>'atacado','sources'=>array('Club de Compras Atacadista')),
					array('name'=>'Papelaria & Bazar','slug'=>'papelaria-bazar','sources'=>array('Bazar & Papelaria','Objetos personalizados')),
					array('name'=>'Presentes & Flores','slug'=>'presentes-flores','sources'=>array('Presentes','Floricultura','Cestas Especiais','Artigos para Presentes')),
				),
			),
			array(
				'name' => 'Financeiro & Seguros', 'slug' => 'financeiro-seguros', 'icon' => 'shield',
				'sources' => array('Seguros & Previdência Privada','Consórcio','Financeiro','Seguros'),
				'children' => array(
					array('name'=>'Seguros','slug'=>'seguros','sources'=>array('Seguros','Seguros & Previdência Privada')),
					array('name'=>'Consórcios','slug'=>'consorcios','sources'=>array('Consórcio','Auto','Moto','Residencial')),
					array('name'=>'Investimentos','slug'=>'investimentos','sources'=>array('Aprenda a Investir na Bolsa')),
				),
			),
		);
	}

	private static function normalize_market_source( string $value ): string {
		return sanitize_title( remove_accents( wp_strip_all_tags( self::clean_text( $value ) ) ) );
	}

	private static function market_source_matches( array $normalized_values, array $sources ): bool {
		foreach ( $sources as $source ) {
			$needle = self::normalize_market_source( (string) $source );
			if ( '' !== $needle && in_array( $needle, $normalized_values, true ) ) return true;
		}
		return false;
	}

	public static function market_filter_tokens( array $values ): array {
		$normalized = array_values( array_unique( array_filter( array_map( static function( $value ) {
			return Serben_Utils::normalize_market_source( (string) $value );
		}, $values ) ) ) );
		$tokens = $normalized;

		foreach ( self::market_categories() as $group ) {
			$group_match = self::market_source_matches( $normalized, (array) ( $group['sources'] ?? array() ) );
			foreach ( (array) ( $group['children'] ?? array() ) as $child ) {
				if ( self::market_source_matches( $normalized, (array) ( $child['sources'] ?? array() ) ) ) {
					$group_match = true;
					$tokens[] = (string) $group['slug'] . '--' . (string) $child['slug'];
				}
			}
			if ( $group_match ) $tokens[] = (string) $group['slug'];
		}

		return array_values( array_unique( array_filter( $tokens ) ) );
	}

	public static function market_primary_category( array $values ): array {
		$tokens = self::market_filter_tokens( $values );
		foreach ( self::market_categories() as $group ) {
			if ( in_array( (string) $group['slug'], $tokens, true ) ) {
				return array( 'name' => (string) $group['name'], 'slug' => (string) $group['slug'], 'icon' => (string) $group['icon'] );
			}
		}
		return array( 'name' => 'Benefícios', 'slug' => 'beneficios', 'icon' => 'tag' );
	}

	public static function clear_query_cache(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_serben_query_%', '_transient_timeout_serben_query_%' ) );
	}

	public static function term_color( int $term_id ): string {
		$color = (string) get_term_meta( $term_id, SERBEN_META_PREFIX . 'color', true );
		return preg_match( '/^#[0-9a-f]{6}$/i', $color ) ? $color : '#2f7d63';
	}

	public static function maybe_extract_href( string $value ): string {
		if ( preg_match( '/href=["\']([^"\']+)["\']/i', $value, $m ) ) {
			return esc_url_raw( html_entity_decode( $m[1] ) );
		}
		return self::sanitize_url_or_empty( $value );
	}
}
