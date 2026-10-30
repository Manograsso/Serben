<?php
/**
 * Awin integration: program search + managed logo sync.
 */

defined( 'ABSPATH' ) || exit;

final class Serben_Awin {
	private const OPTION_KEY = 'serben_awin_settings';
	private const CACHE_KEY  = 'serben_awin_programmes_v1';
	private const CACHE_TTL  = 12 * HOUR_IN_SECONDS;

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 25 );
		add_action( 'admin_post_serben_save_awin_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_serben_awin_refresh', array( $this, 'refresh_programmes' ) );
		add_action( 'admin_post_serben_awin_sync_missing', array( $this, 'sync_missing' ) );
		add_action( 'wp_ajax_serben_awin_find_logo', array( $this, 'ajax_find_logo' ) );
		add_action( 'wp_ajax_serben_awin_import_logo', array( $this, 'ajax_import_logo' ) );
	}

	public static function settings(): array {
		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$publisher_id = defined( 'SERBEN_AWIN_PUBLISHER_ID' ) ? (string) SERBEN_AWIN_PUBLISHER_ID : (string) ( $stored['publisher_id'] ?? '' );
		$token = defined( 'SERBEN_AWIN_API_TOKEN' ) ? (string) SERBEN_AWIN_API_TOKEN : (string) ( $stored['api_token'] ?? '' );
		return array(
			'publisher_id' => preg_replace( '/\D+/', '', $publisher_id ),
			'api_token'    => trim( $token ),
			'from_constants' => defined( 'SERBEN_AWIN_PUBLISHER_ID' ) || defined( 'SERBEN_AWIN_API_TOKEN' ),
		);
	}

	public static function is_configured(): bool {
		$s = self::settings();
		return '' !== $s['publisher_id'] && '' !== $s['api_token'];
	}

	public static function admin_script_data(): array {
		return array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'awinNonce'  => wp_create_nonce( 'serben_awin_admin' ),
			'awinReady'  => self::is_configured(),
			'awinPage'   => admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-awin' ),
		);
	}

	public function admin_menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . SERBEN_POST_TYPE,
			__( 'Awin / Logos', 'serben-clube-beneficios' ),
			__( 'Awin / Logos', 'serben-clube-beneficios' ),
			'manage_options',
			'serben-awin',
			array( $this, 'render_page' )
		);
	}

	private static function normalize_program( array $item ): array {
		return array(
			'id'              => absint( $item['id'] ?? 0 ),
			'name'            => sanitize_text_field( (string) ( $item['name'] ?? '' ) ),
			'logoUrl'         => esc_url_raw( (string) ( $item['logoUrl'] ?? '' ) ),
			'displayUrl'      => esc_url_raw( (string) ( $item['displayUrl'] ?? '' ) ),
			'clickThroughUrl' => esc_url_raw( (string) ( $item['clickThroughUrl'] ?? '' ) ),
			'status'          => sanitize_key( (string) ( $item['status'] ?? '' ) ),
			'linkStatus'      => sanitize_key( (string) ( $item['linkStatus'] ?? '' ) ),
		);
	}

	/** @return array|WP_Error */
	public static function programmes( bool $force = false ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'serben_awin_not_configured', __( 'Configure o Publisher ID e o token da Awin primeiro.', 'serben-clube-beneficios' ) );
		}
		if ( ! $force ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) return $cached;
		}
		$s = self::settings();
		$url = add_query_arg( array( 'relationship' => 'joined' ), 'https://api.awin.com/publishers/' . rawurlencode( $s['publisher_id'] ) . '/programmes' );
		$response = wp_remote_get( $url, array(
			'timeout' => 25,
			'redirection' => 3,
			'headers' => array(
				'Authorization' => 'Bearer ' . $s['api_token'],
				'Accept' => 'application/json',
				'User-Agent' => 'ClubSerben/' . SERBEN_PLUGIN_VERSION . '; ' . home_url( '/' ),
			),
		) );
		if ( is_wp_error( $response ) ) return $response;
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			$message = $code === 401 || $code === 403
				? __( 'A Awin recusou a autenticação. Confira Publisher ID, token e permissões da conta.', 'serben-clube-beneficios' )
				: sprintf( __( 'Awin respondeu com HTTP %d.', 'serben-clube-beneficios' ), $code );
			return new WP_Error( 'serben_awin_http', $message, array( 'status' => $code, 'body' => wp_strip_all_tags( $body ) ) );
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) return new WP_Error( 'serben_awin_json', __( 'A resposta da Awin não pôde ser interpretada.', 'serben-clube-beneficios' ) );
		$programmes = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) continue;
			$p = self::normalize_program( $item );
			if ( $p['id'] && $p['name'] ) $programmes[] = $p;
		}
		set_transient( self::CACHE_KEY, $programmes, self::CACHE_TTL );
		update_option( 'serben_awin_last_sync', current_time( 'mysql' ), false );
		return $programmes;
	}

	private static function normalize_name( string $value ): string {
		$value = strtolower( remove_accents( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) );
		$value = preg_replace( '/\b(brasil|brazil|br|loja|store|oficial|online)\b/u', ' ', $value );
		$value = preg_replace( '/[^a-z0-9]+/u', ' ', $value );
		return trim( preg_replace( '/\s+/', ' ', $value ) );
	}

	/** @return array|WP_Error */
	public static function find_programmes( string $query, int $advertiser_id = 0 ) {
		$list = self::programmes();
		if ( is_wp_error( $list ) ) return $list;
		if ( $advertiser_id ) {
			foreach ( $list as $program ) if ( absint( $program['id'] ) === $advertiser_id ) return array( $program + array( 'score' => 100 ) );
		}
		$needle = self::normalize_name( $query );
		if ( '' === $needle ) return array();
		$ranked = array();
		foreach ( $list as $program ) {
			$name = self::normalize_name( (string) $program['name'] );
			if ( '' === $name ) continue;
			$score = 0.0;
			if ( $name === $needle ) $score = 100;
			elseif ( str_starts_with( $name, $needle ) || str_starts_with( $needle, $name ) ) $score = 92;
			elseif ( false !== strpos( $name, $needle ) || false !== strpos( $needle, $name ) ) $score = 84;
			else similar_text( $needle, $name, $score );
			if ( $score >= 48 ) $ranked[] = $program + array( 'score' => round( $score, 1 ) );
		}
		usort( $ranked, static fn( $a, $b ) => ( $b['score'] <=> $a['score'] ) ?: ( $a['name'] <=> $b['name'] ) );
		return array_slice( $ranked, 0, 6 );
	}

	/** @return int|WP_Error */
	public static function import_logo( int $post_id, array $program, bool $cleanup_old = true ) {
		if ( SERBEN_POST_TYPE !== get_post_type( $post_id ) ) return new WP_Error( 'serben_awin_post', __( 'Parceiro inválido.', 'serben-clube-beneficios' ) );
		$logo_url = esc_url_raw( (string) ( $program['logoUrl'] ?? '' ) );
		if ( ! $logo_url ) return new WP_Error( 'serben_awin_no_logo', __( 'Esse programa da Awin não possui logo disponível.', 'serben-clube-beneficios' ) );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$old_id = absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', true ) );
		$attachment_id = media_sideload_image( $logo_url, $post_id, 'Logo Awin - ' . get_the_title( $post_id ), 'id' );
		if ( is_wp_error( $attachment_id ) ) return $attachment_id;
		$attachment_id = absint( $attachment_id );
		wp_update_post( array( 'ID' => $attachment_id, 'post_title' => 'Logo - ' . get_the_title( $post_id ) . ' (Awin)' ) );
		Serben_Utils::mark_managed_logo( $attachment_id, $post_id, 'awin' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', $attachment_id );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_mode', 'local' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_origin', 'awin' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'awin_synced_at', current_time( 'mysql' ) );
		update_post_meta( $post_id, 'awin_logo_url', $logo_url );
		if ( ! empty( $program['id'] ) ) update_post_meta( $post_id, 'awin_advertiser_id', absint( $program['id'] ) );
		if ( ! empty( $program['name'] ) ) update_post_meta( $post_id, 'awin_program_name', sanitize_text_field( $program['name'] ) );
		if ( ! empty( $program['clickThroughUrl'] ) ) update_post_meta( $post_id, 'awin_click_through_url', esc_url_raw( $program['clickThroughUrl'] ) );
		delete_post_meta( $post_id, '_serben_brand_color_auto' );
		delete_post_meta( $post_id, '_serben_brand_color_auto_logo_id' );
		if ( $cleanup_old && $old_id && $old_id !== $attachment_id ) Serben_Utils::maybe_delete_managed_logo( $old_id, $post_id );
		Serben_Utils::clear_query_cache();
		return $attachment_id;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		$s = self::settings();
		$cached = get_transient( self::CACHE_KEY );
		$last = (string) get_option( 'serben_awin_last_sync', '' );
		$notice = isset( $_GET['serben_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['serben_notice'] ) ) : '';
		if ( $notice ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		?>
		<div class="wrap serben-admin-page">
			<h1><?php esc_html_e( 'Awin / Logos oficiais', 'serben-clube-beneficios' ); ?></h1>
			<div class="serben-card">
				<h2><?php esc_html_e( 'Conexão com a Awin', 'serben-clube-beneficios' ); ?></h2>
				<p><?php esc_html_e( 'O plugin consulta os programas Awin associados à sua conta de publisher, usa o logoUrl oficial e salva uma cópia local na Biblioteca de Mídia. O logo não fica dependente do servidor da Awin no front-end.', 'serben-clube-beneficios' ); ?></p>
				<?php if ( ! empty( $s['from_constants'] ) ) : ?><p><strong><?php esc_html_e( 'Credenciais definidas no wp-config.php.', 'serben-clube-beneficios' ); ?></strong></p><?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'serben_save_awin_settings' ); ?>
					<input type="hidden" name="action" value="serben_save_awin_settings">
					<table class="form-table"><tbody>
					<tr><th><label for="serben-awin-publisher">Publisher ID</label></th><td><input id="serben-awin-publisher" class="regular-text" name="publisher_id" value="<?php echo esc_attr( $s['publisher_id'] ); ?>" <?php disabled( defined( 'SERBEN_AWIN_PUBLISHER_ID' ) ); ?>></td></tr>
					<tr><th><label for="serben-awin-token">API token</label></th><td><input id="serben-awin-token" type="password" class="regular-text" name="api_token" value="" placeholder="<?php echo esc_attr( $s['api_token'] ? 'Token já configurado — deixe vazio para manter' : 'Cole seu token da Awin' ); ?>" <?php disabled( defined( 'SERBEN_AWIN_API_TOKEN' ) ); ?> autocomplete="new-password"><p class="description">Recomendado em produção: definir SERBEN_AWIN_PUBLISHER_ID e SERBEN_AWIN_API_TOKEN no wp-config.php.</p></td></tr>
					</tbody></table>
					<?php submit_button( __( 'Salvar integração', 'serben-clube-beneficios' ) ); ?>
				</form>
				<?php if ( self::is_configured() ) : ?>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=serben_awin_refresh' ), 'serben_awin_refresh' ) ); ?>">Testar conexão / atualizar programas</a></p>
				<p class="description"><?php echo esc_html( is_array( $cached ) ? count( $cached ) . ' programas em cache.' : 'Nenhum cache carregado.' ); ?> <?php echo $last ? 'Última atualização: ' . esc_html( $last ) . '.' : ''; ?></p>
				<?php endif; ?>
			</div>
			<div class="serben-card">
				<h2><?php esc_html_e( 'Preencher logos ausentes', 'serben-clube-beneficios' ); ?></h2>
				<p><?php esc_html_e( 'Importa até 20 logos por execução. Para evitar logo incorreto, a automação só usa ID Awin exato ou nome normalizado com correspondência forte. Logos removidos intencionalmente são respeitados.', 'serben-clube-beneficios' ); ?></p>
				<?php if ( self::is_configured() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Buscar e importar até 20 logos ausentes pela Awin?');">
					<?php wp_nonce_field( 'serben_awin_sync_missing' ); ?><input type="hidden" name="action" value="serben_awin_sync_missing">
					<?php submit_button( __( 'Sincronizar próximos logos ausentes', 'serben-clube-beneficios' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php else : ?><p><em>Configure a conexão acima primeiro.</em></p><?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		check_admin_referer( 'serben_save_awin_settings' );
		$current = get_option( self::OPTION_KEY, array() );
		$current = is_array( $current ) ? $current : array();
		if ( ! defined( 'SERBEN_AWIN_PUBLISHER_ID' ) ) $current['publisher_id'] = preg_replace( '/\D+/', '', (string) ( $_POST['publisher_id'] ?? '' ) );
		if ( ! defined( 'SERBEN_AWIN_API_TOKEN' ) && isset( $_POST['api_token'] ) && '' !== trim( (string) $_POST['api_token'] ) ) $current['api_token'] = sanitize_text_field( wp_unslash( $_POST['api_token'] ) );
		update_option( self::OPTION_KEY, $current, false );
		delete_transient( self::CACHE_KEY );
		$this->redirect_notice( 'Integração Awin salva.' );
	}

	public function refresh_programmes(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		check_admin_referer( 'serben_awin_refresh' );
		$result = self::programmes( true );
		if ( is_wp_error( $result ) ) $this->redirect_notice( 'Erro Awin: ' . $result->get_error_message() );
		$this->redirect_notice( sprintf( 'Conexão OK. %d programas Awin carregados.', count( $result ) ) );
	}

	public function sync_missing(): void {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		check_admin_referer( 'serben_awin_sync_missing' );
		$programmes = self::programmes();
		if ( is_wp_error( $programmes ) ) $this->redirect_notice( 'Erro Awin: ' . $programmes->get_error_message() );
		$ids = get_posts( array(
			'post_type' => SERBEN_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 80, 'fields' => 'ids', 'no_found_rows' => true,
			'meta_query' => array( 'relation' => 'OR', array( 'key' => SERBEN_META_PREFIX . 'logo_id', 'compare' => 'NOT EXISTS' ), array( 'key' => SERBEN_META_PREFIX . 'logo_id', 'value' => '0' ) ),
		) );
		$imported = 0; $skipped = 0; $checked = 0;
		foreach ( $ids as $post_id ) {
			if ( $checked >= 20 ) break;
			if ( 'none' === (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_mode', true ) ) continue;
			if ( ! Serben_Utils::partner_is_online( $post_id ) ) continue;
			$checked++;
			$awin_id = absint( get_post_meta( $post_id, 'awin_advertiser_id', true ) );
			$matches = self::find_programmes( get_the_title( $post_id ), $awin_id );
			if ( is_wp_error( $matches ) || empty( $matches ) ) { $skipped++; continue; }
			$best = $matches[0];
			if ( ! $awin_id && (float) ( $best['score'] ?? 0 ) < 92 ) { $skipped++; continue; }
			$result = self::import_logo( absint( $post_id ), $best, true );
			if ( is_wp_error( $result ) ) $skipped++; else $imported++;
		}
		$this->redirect_notice( sprintf( 'Awin: %d logos importados; %d não tiveram correspondência segura. Execute novamente para o próximo lote.', $imported, $skipped ) );
	}

	public function ajax_find_logo(): void {
		check_ajax_referer( 'serben_awin_admin', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( array( 'message' => 'Permissão insuficiente.' ), 403 );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( SERBEN_POST_TYPE !== get_post_type( $post_id ) ) wp_send_json_error( array( 'message' => 'Parceiro inválido.' ), 400 );
		$awin_id = absint( get_post_meta( $post_id, 'awin_advertiser_id', true ) );
		$result = self::find_programmes( get_the_title( $post_id ), $awin_id );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( array( 'programmes' => $result ) );
	}

	public function ajax_import_logo(): void {
		check_ajax_referer( 'serben_awin_admin', 'nonce' );
		if ( ! current_user_can( 'upload_files' ) ) wp_send_json_error( array( 'message' => 'Permissão insuficiente.' ), 403 );
		$post_id = absint( $_POST['post_id'] ?? 0 );
		$advertiser_id = absint( $_POST['advertiser_id'] ?? 0 );
		$programmes = self::programmes();
		if ( is_wp_error( $programmes ) ) wp_send_json_error( array( 'message' => $programmes->get_error_message() ), 400 );
		$program = null;
		foreach ( $programmes as $p ) if ( absint( $p['id'] ) === $advertiser_id ) { $program = $p; break; }
		if ( ! $program ) wp_send_json_error( array( 'message' => 'Programa Awin não encontrado.' ), 404 );
		$result = self::import_logo( $post_id, $program, true );
		if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		wp_send_json_success( array(
			'attachmentId' => absint( $result ),
			'url' => wp_get_attachment_image_url( absint( $result ), 'medium' ),
			'advertiserId' => $advertiser_id,
			'name' => (string) $program['name'],
		) );
	}

	private function redirect_notice( string $message ): void {
		wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( $message ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-awin' ) ) );
		exit;
	}
}
