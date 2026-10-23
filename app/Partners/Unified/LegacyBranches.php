<?php
/**
 * Branch repository backed by a custom table and mirrored post meta.
 */

defined( 'ABSPATH' ) || exit;

class CSC_Unified_Branches {
	public function init(): void {
		add_action( 'before_delete_post', array( $this, 'delete_for_post' ) );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'serben_filiais';
	}

	public static function sanitize_branch( array $branch ): array {
		return array(
			'unit_name'   => isset( $branch['unit_name'] ) ? sanitize_text_field( wp_unslash( $branch['unit_name'] ) ) : '',
			'address'     => isset( $branch['address'] ) ? sanitize_textarea_field( wp_unslash( $branch['address'] ) ) : '',
			'city'        => isset( $branch['city'] ) ? sanitize_text_field( wp_unslash( $branch['city'] ) ) : '',
			'state'       => isset( $branch['state'] ) ? sanitize_text_field( wp_unslash( $branch['state'] ) ) : '',
			'zipcode'     => isset( $branch['zipcode'] ) ? sanitize_text_field( wp_unslash( $branch['zipcode'] ) ) : '',
			'phone'       => isset( $branch['phone'] ) ? CSC_Unified_Utils::sanitize_phone( wp_unslash( $branch['phone'] ) ) : '',
			'whatsapp'    => isset( $branch['whatsapp'] ) ? CSC_Unified_Utils::sanitize_phone( wp_unslash( $branch['whatsapp'] ) ) : '',
			'hours'       => isset( $branch['hours'] ) ? sanitize_textarea_field( wp_unslash( $branch['hours'] ) ) : '',
			'maps_url'    => isset( $branch['maps_url'] ) ? CSC_Unified_Utils::sanitize_url_or_empty( wp_unslash( $branch['maps_url'] ) ) : '',
			'responsible' => isset( $branch['responsible'] ) ? sanitize_text_field( wp_unslash( $branch['responsible'] ) ) : '',
		);
	}

	public static function save_for_post( int $post_id, array $branches ): void {
		global $wpdb;
		$table = self::table();
		$now   = current_time( 'mysql' );

		$clean = array();
		foreach ( $branches as $branch ) {
			if ( ! is_array( $branch ) ) {
				continue;
			}
			$branch = self::sanitize_branch( $branch );
			if ( '' === implode( '', array_map( 'strval', $branch ) ) ) {
				continue;
			}
			$clean[] = $branch;
		}

		$wpdb->delete( $table, array( 'partner_id' => $post_id ), array( '%d' ) );

		foreach ( $clean as $branch ) {
			$wpdb->insert(
				$table,
				array_merge(
					$branch,
					array(
						'partner_id'  => $post_id,
						'created_at'  => $now,
						'updated_at'  => $now,
					)
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		update_post_meta( $post_id, SERBEN_META_PREFIX . 'filiais', $clean );
	}

	public static function get_for_post( int $post_id ): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE partner_id = %d ORDER BY id ASC", $post_id ), ARRAY_A );
		if ( ! empty( $rows ) ) {
			return array_map(
				static function ( array $row ): array {
					return array(
						'unit_name'   => (string) $row['unit_name'],
						'address'     => (string) $row['address'],
						'city'        => (string) $row['city'],
						'state'       => (string) $row['state'],
						'zipcode'     => (string) $row['zipcode'],
						'phone'       => (string) $row['phone'],
						'whatsapp'    => (string) $row['whatsapp'],
						'hours'       => (string) $row['hours'],
						'maps_url'    => (string) $row['maps_url'],
						'responsible' => (string) $row['responsible'],
					);
				},
				$rows
			);
		}

		$meta = get_post_meta( $post_id, SERBEN_META_PREFIX . 'filiais', true );
		return is_array( $meta ) ? $meta : array();
	}

	public static function cities(): array {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_col( "SELECT DISTINCT city FROM {$table} WHERE city <> '' ORDER BY city ASC" );
		return array_map( 'sanitize_text_field', $rows ?: array() );
	}

	public static function partner_ids_by_city( string $city ): array {
		global $wpdb;
		$city = sanitize_text_field( $city );
		if ( '' === $city ) {
			return array();
		}
		$table = self::table();
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT partner_id FROM {$table} WHERE city = %s", $city ) );
		return array_map( 'absint', $ids ?: array() );
	}

	public function delete_for_post( int $post_id ): void {
		if ( SERBEN_POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( self::table(), array( 'partner_id' => $post_id ), array( '%d' ) );
	}
}
