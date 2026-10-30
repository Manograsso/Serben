<?php
/**
 * AJAX handlers.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Ajax {
	public function init(): void {
		add_action( 'wp_ajax_serben_search_partners', array( $this, 'search' ) );
		add_action( 'wp_ajax_nopriv_serben_search_partners', array( $this, 'search' ) );
		add_action( 'wp_ajax_serben_track_whatsapp', array( $this, 'track_whatsapp' ) );
		add_action( 'wp_ajax_nopriv_serben_track_whatsapp', array( $this, 'track_whatsapp' ) );
		add_action( 'wp_ajax_serben_redeem_benefit', array( $this, 'redeem_benefit' ) );
		add_action( 'wp_ajax_nopriv_serben_redeem_benefit', array( $this, 'redeem_benefit' ) );
	}

	public function search(): void {
		check_ajax_referer( 'serben_public_nonce', 'nonce' );
		$args = array(
			's'         => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
			'categoria' => isset( $_POST['categoria'] ) ? sanitize_title( wp_unslash( $_POST['categoria'] ) ) : '',
			'cidade'    => isset( $_POST['cidade'] ) ? sanitize_text_field( wp_unslash( $_POST['cidade'] ) ) : '',
			'tipo'      => isset( $_POST['tipo'] ) ? sanitize_key( wp_unslash( $_POST['tipo'] ) ) : '',
			'destaque'  => isset( $_POST['destaque'] ) ? sanitize_text_field( wp_unslash( $_POST['destaque'] ) ) : '',
			'page'      => isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1,
			'per_page'  => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 12,
		);
		$data = Serben_Shortcodes::query_partners( $args );
		wp_send_json_success( $data );
	}

	public function track_whatsapp(): void {
		check_ajax_referer( 'serben_public_nonce', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || SERBEN_POST_TYPE !== get_post_type( $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Parceiro inválido.', 'serben-clube-beneficios' ) ), 400 );
		}
		$key     = SERBEN_META_PREFIX . 'whatsapp_clicks';
		$current = absint( get_post_meta( $post_id, $key, true ) );
		update_post_meta( $post_id, $key, $current + 1 );
		wp_send_json_success( array( 'clicks' => $current + 1 ) );
	}

	public function redeem_benefit(): void {
		check_ajax_referer( 'serben_public_nonce', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array(
					'message'    => __( 'Entre na Área do Associado para resgatar.', 'serben-clube-beneficios' ),
					'error_code' => 'login_required',
					'login_url'  => Serben_Connect_Bridge::login_url( $post_id ),
				),
				401
			);
		}

		$result = Serben_Connect_Bridge::redeem( $post_id );
		if ( empty( $result['ok'] ) ) {
			$status = isset( $result['http_status'] ) ? absint( $result['http_status'] ) : 400;
			wp_send_json_error(
				array(
					'message'       => sanitize_text_field( (string) ( $result['message'] ?? __( 'Não foi possível concluir o resgate.', 'serben-clube-beneficios' ) ) ),
					'error_code'    => sanitize_key( (string) ( $result['error_code'] ?? 'redemption_error' ) ),
					'balance'       => isset( $result['balance'] ) ? (int) $result['balance'] : null,
					'required'      => isset( $result['required'] ) ? (int) $result['required'] : null,
					'login_url'     => isset( $result['login_url'] ) ? esc_url_raw( (string) $result['login_url'] ) : '',
				),
				$status >= 400 && $status <= 599 ? $status : 400
			);
		}

		if ( empty( $result['already_redeemed'] ) ) {
			$key     = SERBEN_META_PREFIX . 'resgate_clicks';
			$current = absint( get_post_meta( $post_id, $key, true ) );
			update_post_meta( $post_id, $key, $current + 1 );
		}

		$record  = is_array( $result['record'] ?? null ) ? $result['record'] : array();
		$voucher = (string) ( $result['voucher_code'] ?? ( $record['voucher_code'] ?? '' ) );
		wp_send_json_success(
			array(
				'message'          => sanitize_text_field( (string) ( $result['message'] ?? __( 'Resgate realizado com sucesso.', 'serben-clube-beneficios' ) ) ),
				'already_redeemed' => ! empty( $result['already_redeemed'] ),
				'voucher_code'     => sanitize_text_field( $voucher ),
				'points_cost'      => isset( $result['points_cost'] ) ? (int) $result['points_cost'] : (int) ( $record['points_cost'] ?? 0 ),
				'balance_after'    => isset( $result['balance_after'] ) ? (int) $result['balance_after'] : ( isset( $record['balance_after'] ) ? (int) $record['balance_after'] : null ),
				'external_reference' => sanitize_text_field( (string) ( $result['external_reference'] ?? ( $record['external_reference'] ?? '' ) ) ),
			)
		);
	}
}
