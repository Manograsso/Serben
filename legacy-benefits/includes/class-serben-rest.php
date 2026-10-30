<?php
/**
 * REST API routes.
 */

defined( 'ABSPATH' ) || exit;

class Serben_REST {
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			'serben/v1',
			'/parceiros',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'partners' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					's'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'categoria' => array( 'sanitize_callback' => 'sanitize_title' ),
					'tipo'      => array( 'sanitize_callback' => 'sanitize_key' ),
					'cidade'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'page'      => array( 'sanitize_callback' => 'absint' ),
					'per_page'  => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);

		register_rest_route(
			'serben/v1',
			'/parceiros/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'partner' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function partners( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			's'         => (string) $request->get_param( 's' ),
			'categoria' => (string) $request->get_param( 'categoria' ),
			'tipo'      => (string) $request->get_param( 'tipo' ),
			'cidade'    => (string) $request->get_param( 'cidade' ),
			'page'      => absint( $request->get_param( 'page' ) ?: 1 ),
			'per_page'  => absint( $request->get_param( 'per_page' ) ?: 12 ),
		);

		$query_args = array(
			'post_type'      => SERBEN_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 48, $args['per_page'] ) ),
			'paged'          => max( 1, $args['page'] ),
			's'              => sanitize_text_field( $args['s'] ),
		);

		$meta_query = array();
		if ( in_array( $args['tipo'], array( 'fisico', 'online' ), true ) ) {
			$meta_query[] = array(
				'key'   => SERBEN_META_PREFIX . 'tipo',
				'value' => $args['tipo'],
			);
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query;
		}
		if ( $args['categoria'] ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => SERBEN_TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $args['categoria'] ),
				),
			);
		}
		if ( $args['cidade'] ) {
			$ids = Serben_Branches::partner_ids_by_city( $args['cidade'] );
			$query_args['post__in'] = $ids ?: array( 0 );
		}

		$q     = new WP_Query( $query_args );
		$items = array_map( array( $this, 'format_partner' ), $q->posts );
		return rest_ensure_response(
			array(
				'total' => absint( $q->found_posts ),
				'pages' => absint( $q->max_num_pages ),
				'items' => $items,
			)
		);
	}

	public function partner( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$post_id = absint( $request['id'] );
		$post    = get_post( $post_id );
		if ( ! $post || SERBEN_POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return new WP_Error( 'serben_not_found', __( 'Parceiro não encontrado.', 'serben-clube-beneficios' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->format_partner( $post ) );
	}

	private function format_partner( WP_Post $post ): array {
		$terms   = get_the_terms( $post->ID, SERBEN_TAXONOMY );
		return array(
			'id'          => $post->ID,
			'name'        => get_the_title( $post ),
			'url'         => get_permalink( $post ),
			'type'        => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'tipo', true ),
			'description' => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'descricao', true ),
			'discount'    => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'desconto', true ),
			'online_discount' => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'desconto_online', true ),
			'offer_title'     => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'titulo_oferta', true ),
			'points_cost'     => absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'custo_bumes', true ) ),
			'limit_per_user'  => max( 1, absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'limite_resgates', true ) ) ?: 1 ),
			'points_label'    => Serben_Connect_Bridge::points_label(),
			'redemption_ready'=> Serben_Connect_Bridge::redemption_ready(),
			'valid_until'     => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'validade', true ),
			'redemption_note' => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'resgate_observacao', true ),
			'logo'        => Serben_Utils::partner_logo_url( $post->ID, 'large' ),
			'categories'  => is_wp_error( $terms ) || empty( $terms ) ? array() : wp_list_pluck( $terms, 'name' ),
			'branches'    => Serben_Branches::get_for_post( $post->ID ),
			'social'      => array(
				'site'      => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'site', true ),
				'whatsapp'  => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'whatsapp', true ),
				'instagram' => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'instagram', true ),
				'facebook'  => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'facebook', true ),
				'youtube'   => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'youtube', true ),
				'tiktok'    => (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'tiktok', true ),
			),
		);
	}
}
