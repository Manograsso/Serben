<?php
/**
 * Activation/deactivation routines.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Activator {
	public static function activate(): void {
		( new Serben_Post_Types() )->register();
		self::create_tables();
		self::seed_categories();
		flush_rewrite_rules();
		update_option( 'serben_clube_beneficios_rewrite_version', SERBEN_PLUGIN_VERSION, false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = $wpdb->prefix . 'serben_filiais';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			partner_id bigint(20) unsigned NOT NULL,
			unit_name varchar(190) NOT NULL DEFAULT '',
			address text NULL,
			city varchar(120) NOT NULL DEFAULT '',
			state varchar(80) NOT NULL DEFAULT '',
			zipcode varchar(30) NOT NULL DEFAULT '',
			phone varchar(80) NOT NULL DEFAULT '',
			whatsapp varchar(80) NOT NULL DEFAULT '',
			hours text NULL,
			maps_url text NULL,
			responsible varchar(190) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY partner_id (partner_id),
			KEY city (city),
			KEY state (state)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function seed_categories(): void {
		$categories = array(
			'Academia'    => array( 'icon' => '🏋️', 'color' => '#2f7d63', 'order' => 10 ),
			'Saúde'      => array( 'icon' => '🩺', 'color' => '#0ea5a4', 'order' => 20 ),
			'Alimentação' => array( 'icon' => '🍽️', 'color' => '#e07a35', 'order' => 30 ),
			'Beleza'      => array( 'icon' => '✨', 'color' => '#c05299', 'order' => 40 ),
			'Educação'    => array( 'icon' => '🎓', 'color' => '#3b65d8', 'order' => 50 ),
			'Automotivo'  => array( 'icon' => '🚗', 'color' => '#444b5a', 'order' => 60 ),
			'Moda'        => array( 'icon' => '👗', 'color' => '#9c4dcc', 'order' => 70 ),
			'Tecnologia'  => array( 'icon' => '💻', 'color' => '#0875be', 'order' => 80 ),
			'Hotelaria'   => array( 'icon' => '🏨', 'color' => '#b7791f', 'order' => 90 ),
		);

		foreach ( $categories as $name => $meta ) {
			$term = term_exists( $name, SERBEN_TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $name, SERBEN_TAXONOMY, array( 'slug' => sanitize_title( $name ) ) );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? absint( $term['term_id'] ) : absint( $term );
				update_term_meta( $term_id, SERBEN_META_PREFIX . 'icon', $meta['icon'] );
				update_term_meta( $term_id, SERBEN_META_PREFIX . 'color', $meta['color'] );
				update_term_meta( $term_id, SERBEN_META_PREFIX . 'order', absint( $meta['order'] ) );
			}
		}
	}
}
