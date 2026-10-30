<?php
/**
 * Plugin bootstrap.
 */

defined( 'ABSPATH' ) || exit;

final class Serben_Plugin {
	private static ?Serben_Plugin $instance = null;

	public static function instance(): Serben_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		load_plugin_textdomain( 'serben-clube-beneficios', false, dirname( plugin_basename( SERBEN_PLUGIN_FILE ) ) . '/languages' );

		( new Serben_Post_Types() )->init();
		( new Serben_Term_Meta() )->init();
		( new Serben_Branches() )->init();
		// AWIN legado desativado: Connect e o unico mecanismo de rastreamento. 
		( new Serben_Meta_Boxes() )->init();
		( new Serben_Admin() )->init();
		( new Serben_Shortcodes() )->init();
		( new Serben_Ajax() )->init();
		( new Serben_REST() )->init();
		( new Serben_Export() )->init();
		( new Serben_Cover_Studio() )->init();
		( new Serben_Favorites() )->init();

		// Nao executar migracao de midias automaticamente em instalacoes existentes.
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		add_filter( 'template_include', array( $this, 'template_include' ), 99 );
		add_action( 'template_redirect', array( $this, 'track_partner_view' ) );
		add_action( 'admin_notices', array( $this, 'cover_notice' ) );
	}


	/** Migração única: separa logo de imagem destacada e registra o modo canônico. */
	public function maybe_migrate_media_model(): void {
		$version = (string) get_option( 'serben_media_model_version', '' );
		if ( version_compare( $version, '3.1.0', '>=' ) ) return;
		$ids = get_posts( array('post_type'=>SERBEN_POST_TYPE,'post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true) );
		foreach ( $ids as $id ) {
			// Marca também uploads antigos do importador (filhos do parceiro com "Logo" no título),
			// para que a limpeza manual consiga remover duplicatas órfãs com segurança.
			$children = get_posts( array('post_type'=>'attachment','post_status'=>'inherit','post_parent'=>$id,'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true) );
			foreach ( $children as $attachment_id ) {
				$title = (string) get_the_title( $attachment_id );
				if ( false !== stripos( $title, 'logo' ) ) Serben_Utils::mark_managed_logo( absint( $attachment_id ), absint( $id ), 'legacy-import' );
			}
			$logo_id = absint( get_post_meta( $id, SERBEN_META_PREFIX . 'logo_id', true ) );
			$mode = (string) get_post_meta( $id, SERBEN_META_PREFIX . 'logo_mode', true );
			if ( $logo_id ) {
				if ( '' === $mode ) update_post_meta( $id, SERBEN_META_PREFIX . 'logo_mode', 'local' );
				if ( get_post_thumbnail_id( $id ) === $logo_id ) delete_post_thumbnail( $id );
				$attachment = get_post( $logo_id );
				if ( $attachment && 'attachment' === $attachment->post_type && absint( $attachment->post_parent ) === absint( $id ) && false !== stripos( $attachment->post_title, 'logo' ) ) {
					Serben_Utils::mark_managed_logo( $logo_id, $id, 'legacy-import' );
				}
			} elseif ( '' === $mode ) {
				update_post_meta( $id, SERBEN_META_PREFIX . 'logo_mode', 'legacy' );
			}
		}
		update_option( 'serben_media_model_version', '3.1.0', false );
		Serben_Utils::clear_query_cache();
	}


	public function cover_notice(): void {
		if ( empty( $_GET['serben_cover_notice'] ) || ! current_user_can( 'edit_posts' ) ) return;
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['serben_cover_notice'] ) ) ) . '</p></div>';
	}

	public function maybe_flush_rewrite_rules(): void {
		$stored_version = (string) get_option( 'serben_clube_beneficios_rewrite_version', '' );

		if ( SERBEN_PLUGIN_VERSION === $stored_version ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'serben_clube_beneficios_rewrite_version', SERBEN_PLUGIN_VERSION, false );
	}

	public function enqueue_public_assets(): void {
		wp_register_style( 'serben-inter-font', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap', array(), null );

		// Os ícones públicos usam SVGs locais do Font Awesome Free via CSS mask.
		// Assim não há dependência de webfont/CDN/Elementor para os ícones.
		wp_register_style( 'serben-public', SERBEN_PLUGIN_URL . 'assets/css/public.css', array( 'serben-inter-font' ), SERBEN_PLUGIN_VERSION );
		wp_register_script( 'serben-public', SERBEN_PLUGIN_URL . 'assets/js/public.js', array( 'jquery' ), SERBEN_PLUGIN_VERSION, true );

		// Montserrat e o CSS base são carregados cedo para que shortcodes/Elementor
		// renderizados depois do wp_head não fiquem sem ícones ou estilos essenciais.
		wp_enqueue_style( 'serben-inter-font' );
		wp_enqueue_style( 'serben-public' );
		wp_localize_script(
			'serben-public',
			'SerbenPublic',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'serben_public_nonce' ),
				'i18n'    => array(
					'loading'       => __( 'Carregando...', 'serben-clube-beneficios' ),
					'empty'         => __( 'Nenhum parceiro encontrado.', 'serben-clube-beneficios' ),
					'copied'        => __( 'Voucher copiado!', 'serben-clube-beneficios' ),
					'copyError'     => __( 'Não foi possível copiar automaticamente. Copie o código manualmente.', 'serben-clube-beneficios' ),
					'confirmRedeem' => __( 'Confirmar o resgate? Os Bumes serão debitados do seu saldo.', 'serben-clube-beneficios' ),
					'redeeming'     => __( 'Processando resgate...', 'serben-clube-beneficios' ),
					'redeemSuccess' => __( 'Resgate realizado com sucesso.', 'serben-clube-beneficios' ),
					'genericError'  => __( 'Não foi possível concluir o resgate.', 'serben-clube-beneficios' ),
				),
			)
		);

		if ( is_singular( SERBEN_POST_TYPE ) || is_post_type_archive( SERBEN_POST_TYPE ) ) {
			wp_enqueue_style( 'serben-public' );
			wp_enqueue_script( 'serben-public' );
		}
	}

	public function template_include( string $template ): string {
		if ( is_post_type_archive( SERBEN_POST_TYPE ) ) {
			$archive = SERBEN_PLUGIN_DIR . 'templates/archive-serben-parceiro.php';
			return file_exists( $archive ) ? $archive : $template;
		}

		if ( ! is_singular( SERBEN_POST_TYPE ) ) {
			return $template;
		}

		$theme_template = locate_template(
			array(
				'single-' . SERBEN_POST_TYPE . '.php',
				'single-serben-parceiro.php',
			)
		);

		if ( $theme_template ) {
			return $theme_template;
		}

		$plugin_template = SERBEN_PLUGIN_DIR . 'templates/single-serben-parceiro.php';

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}

	public function track_partner_view(): void {
		if ( ! is_singular( SERBEN_POST_TYPE ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}

		$key      = SERBEN_META_PREFIX . 'views';
		$current  = absint( get_post_meta( $post_id, $key, true ) );
		$lock_key = 'serben_view_' . $post_id . '_' . md5( Serben_Utils::get_client_ip() );

		if ( false === get_transient( $lock_key ) ) {
			update_post_meta( $post_id, $key, $current + 1 );
			set_transient( $lock_key, 1, HOUR_IN_SECONDS );
		}
	}
}
