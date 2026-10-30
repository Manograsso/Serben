<?php
/**
 * Bridge between the benefits catalogue and Clube Serben Connect.
 */

defined( 'ABSPATH' ) || exit;

final class Serben_Connect_Bridge {
	public static function available(): bool {
		return class_exists( '\\SerbenConnect\\Redemptions\\RedemptionService' );
	}

	public static function points_label(): string {
		if ( class_exists( '\\SerbenConnect\\Support\\Settings' ) ) {
			return (string) \SerbenConnect\Support\Settings::get( 'points_label', 'Bumes' );
		}
		return 'Bumes';
	}

	public static function redemption_ready(): bool {
		if ( ! self::available() || ! class_exists( '\\SerbenConnect\\Support\\Settings' ) ) {
			return false;
		}
		return '1' === (string) \SerbenConnect\Support\Settings::get( 'redemption_enabled', '0' )
			&& '' !== trim( (string) \SerbenConnect\Support\Settings::get( 'redemption_endpoint', '' ) );
	}

	public static function redemption_issue(): string {
		if ( ! self::available() ) {
			return __( 'Integração com o app indisponível.', 'serben-clube-beneficios' );
		}
		if ( ! self::redemption_ready() ) {
			return __( 'O débito de Bumes ainda precisa ser configurado no Serben Connect.', 'serben-clube-beneficios' );
		}
		return '';
	}

	public static function current_balance(): ?int {
		if ( ! self::available() || ! is_user_logged_in() ) {
			return null;
		}
		try {
			return ( new \SerbenConnect\Redemptions\RedemptionService() )->currentBalance( false );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	public static function latest_redemption( int $post_id ): ?array {
		if ( ! self::available() || ! is_user_logged_in() ) {
			return null;
		}
		try {
			return ( new \SerbenConnect\Redemptions\RedemptionService() )->latestForCurrentUser( $post_id );
		} catch ( Throwable $e ) {
			return null;
		}
	}

	public static function redeem( int $post_id ): array {
		if ( ! self::available() ) {
			return array(
				'ok'          => false,
				'message'     => __( 'O plugin Clube Serben Connect precisa estar ativo e atualizado.', 'serben-clube-beneficios' ),
				'error_code'  => 'connect_unavailable',
				'http_status' => 503,
			);
		}

		if ( SERBEN_POST_TYPE !== get_post_type( $post_id ) || 'publish' !== get_post_status( $post_id ) ) {
			return array(
				'ok'          => false,
				'message'     => __( 'Oferta inválida ou indisponível.', 'serben-clube-beneficios' ),
				'error_code'  => 'invalid_offer',
				'http_status' => 404,
			);
		}

		$tipo       = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', true );
		$discount   = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . ( 'online' === $tipo ? 'desconto_online' : 'desconto' ), true );
		$offer_title = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'titulo_oferta', true );
		$cost       = absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'custo_bumes', true ) );
		$limit      = max( 1, absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'limite_resgates', true ) ) ?: 1 );
		$voucher    = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'cupom', true );
		$valid_until = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'validade', true );

		if ( $valid_until && strtotime( $valid_until . ' 23:59:59' ) < current_time( 'timestamp' ) ) {
			return array(
				'ok'          => false,
				'message'     => __( 'Esta oferta já encerrou e não pode mais ser resgatada.', 'serben-clube-beneficios' ),
				'error_code'  => 'offer_expired',
				'http_status' => 410,
			);
		}

		$benefit = array(
			'partner_id'     => $post_id,
			'partner_name'   => get_the_title( $post_id ),
			'benefit_title'  => $offer_title ?: ( $discount ?: get_the_title( $post_id ) ),
			'points_cost'    => $cost,
			'limit_per_user' => $limit,
			'voucher_code'   => $voucher,
			'metadata'       => array(
				'offer_url'    => get_permalink( $post_id ),
				'partner_type' => $tipo,
				'valid_until'  => $valid_until,
				'partner_url'  => (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'link_parceiro', true ),
			),
		);

		try {
			return ( new \SerbenConnect\Redemptions\RedemptionService() )->redeem( $benefit );
		} catch ( Throwable $e ) {
			return array(
				'ok'          => false,
				'message'     => __( 'Não foi possível concluir o resgate. Tente novamente.', 'serben-clube-beneficios' ),
				'error_code'  => 'unexpected_error',
				'http_status' => 500,
			);
		}
	}

	public static function login_url( int $post_id = 0 ): string {
		$redirect = $post_id ? get_permalink( $post_id ) : home_url( '/' );
		return (string) apply_filters( 'serben_benefits_login_url', wp_login_url( $redirect ), $post_id );
	}
}
