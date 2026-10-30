<?php
/**
 * CPT and taxonomy registration.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Post_Types {
	public function init(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'save_post_' . SERBEN_POST_TYPE, array( $this, 'clear_cache_on_save' ), 20 );
		add_action( 'deleted_post', array( $this, 'clear_cache_on_delete' ) );
	}

	public function register(): void {
		register_post_type(
			SERBEN_POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Parceiros', 'serben-clube-beneficios' ),
					'singular_name'         => __( 'Parceiro', 'serben-clube-beneficios' ),
					'add_new_item'          => __( 'Adicionar Parceiro', 'serben-clube-beneficios' ),
					'edit_item'             => __( 'Editar Parceiro', 'serben-clube-beneficios' ),
					'new_item'              => __( 'Novo Parceiro', 'serben-clube-beneficios' ),
					'view_item'             => __( 'Ver Parceiro', 'serben-clube-beneficios' ),
					'search_items'          => __( 'Buscar Parceiros', 'serben-clube-beneficios' ),
					'not_found'             => __( 'Nenhum parceiro encontrado', 'serben-clube-beneficios' ),
					'menu_name'             => __( 'Clube Serben', 'serben-clube-beneficios' ),
				),
				'public'              => true,
				'has_archive'         => 'credenciados',
				'rewrite'             => array(
					'slug'       => 'credenciado',
					'with_front' => false,
				),
				'show_in_rest'        => true,
				'rest_base'           => 'serben-parceiros',
				'menu_icon'           => 'dashicons-awards',
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				'taxonomies'          => array( SERBEN_TAXONOMY ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => false,
			)
		);

		register_taxonomy(
			SERBEN_TAXONOMY,
			SERBEN_POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Categorias', 'serben-clube-beneficios' ),
					'singular_name' => __( 'Categoria', 'serben-clube-beneficios' ),
					'add_new_item'  => __( 'Adicionar Categoria', 'serben-clube-beneficios' ),
					'edit_item'     => __( 'Editar Categoria', 'serben-clube-beneficios' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array(
					'slug'       => 'categoria-beneficio',
					'with_front' => false,
				),
			)
		);
	}

	public function clear_cache_on_save( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		Serben_Utils::clear_query_cache();
	}

	public function clear_cache_on_delete( int $post_id ): void {
		if ( SERBEN_POST_TYPE === get_post_type( $post_id ) ) {
			Serben_Utils::clear_query_cache();
		}
	}
}
