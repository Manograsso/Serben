<?php
/**
 * ZIP logo importer.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Import_Logos {
	public function import( array $file, array $args = array() ): array {
		$args = wp_parse_args( $args, array( 'replace_existing' => false ) );
		$this->validate_file( $file );

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'A extensão ZipArchive do PHP é necessária para importar logos.', 'serben-clube-beneficios' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file['tmp_name'] ) ) {
			wp_die( esc_html__( 'Não foi possível abrir o ZIP.', 'serben-clube-beneficios' ) );
		}

		$partners = $this->partners_index();
		$result   = array( 'linked' => 0, 'skipped' => 0 );

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			$name = isset( $stat['name'] ) ? (string) $stat['name'] : '';
			if ( '' === $name || str_ends_with( $name, '/' ) || ! $this->is_image( $name ) ) {
				continue;
			}

			$match = $this->find_best_match( $name, $partners );
			if ( ! $match || $match['score'] < 58 ) {
				$result['skipped']++;
				continue;
			}

			$post_id = absint( $match['post_id'] );
			$old_logo_id = absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', true ) );
			if ( ! $args['replace_existing'] && absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', true ) ) ) {
				$result['skipped']++;
				continue;
			}

			$tmp = wp_tempnam( basename( $name ) );
			if ( ! $tmp ) {
				$result['skipped']++;
				continue;
			}

			$content = $zip->getFromIndex( $i );
			if ( false === $content ) {
				@unlink( $tmp );
				$result['skipped']++;
				continue;
			}
			file_put_contents( $tmp, $content );

			$file_array = array(
				'name'     => sanitize_file_name( basename( $name ) ),
				'tmp_name' => $tmp,
			);
			$attachment_id = media_handle_sideload( $file_array, $post_id, get_the_title( $post_id ) . ' - Logo' );
			if ( is_wp_error( $attachment_id ) ) {
				@unlink( $tmp );
				$result['skipped']++;
				continue;
			}

			Serben_Utils::mark_managed_logo( absint( $attachment_id ), $post_id, 'zip-import' );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', absint( $attachment_id ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_mode', 'local' );
			delete_post_meta( $post_id, '_serben_brand_color_auto' );
			delete_post_meta( $post_id, '_serben_brand_color_auto_logo_id' );
			if ( get_post_thumbnail_id( $post_id ) === $old_logo_id && $old_logo_id ) delete_post_thumbnail( $post_id );
			if ( $args['replace_existing'] && $old_logo_id && $old_logo_id !== absint( $attachment_id ) ) Serben_Utils::maybe_delete_managed_logo( $old_logo_id, $post_id );
			$result['linked']++;
		}

		$zip->close();
		Serben_Utils::clear_query_cache();
		return $result;
	}

	private function validate_file( array $file ): void {
		if ( ! empty( $file['error'] ) ) {
			wp_die( esc_html__( 'Erro no upload do ZIP.', 'serben-clube-beneficios' ) );
		}
		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		if ( 'zip' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			wp_die( esc_html__( 'Formato inválido. Envie um arquivo .zip.', 'serben-clube-beneficios' ) );
		}
	}

	private function is_image( string $name ): bool {
		return (bool) preg_match( '/\.(jpe?g|png|gif|webp)$/i', $name );
	}

	private function partners_index(): array {
		$q = new WP_Query(
			array(
				'post_type'      => SERBEN_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$index = array();
		foreach ( $q->posts as $post_id ) {
			$title = get_the_title( $post_id );
			$index[] = array(
				'post_id' => absint( $post_id ),
				'title'   => $title,
				'norm'    => Serben_Utils::normalize_string( $title ),
			);
		}
		return $index;
	}

	private function find_best_match( string $zip_path, array $partners ): ?array {
		$parts      = array_values( array_filter( explode( '/', str_replace( '\\', '/', $zip_path ) ) ) );
		$file_base  = pathinfo( end( $parts ), PATHINFO_FILENAME );
		$folder     = count( $parts ) > 1 ? $parts[ count( $parts ) - 2 ] : '';
		$candidates = array_filter( array( Serben_Utils::normalize_string( $file_base ), Serben_Utils::normalize_string( $folder ) ) );

		$best = null;
		foreach ( $partners as $partner ) {
			foreach ( $candidates as $candidate ) {
				$score = $this->score( $candidate, $partner['norm'] );
				if ( null === $best || $score > $best['score'] ) {
					$best = array(
						'post_id' => $partner['post_id'],
						'score'   => $score,
						'file'    => $zip_path,
					);
				}
			}
		}
		return $best;
	}

	private function score( string $a, string $b ): float {
		if ( '' === $a || '' === $b ) {
			return 0;
		}
		if ( $a === $b ) {
			return 100;
		}
		if ( str_contains( $a, $b ) || str_contains( $b, $a ) ) {
			return 88;
		}
		similar_text( $a, $b, $percent );
		$tokens_a = array_filter( explode( ' ', $a ) );
		$tokens_b = array_filter( explode( ' ', $b ) );
		$common   = array_intersect( $tokens_a, $tokens_b );
		$token_score = $tokens_b ? ( count( $common ) / max( count( $tokens_b ), 1 ) ) * 100 : 0;
		return max( $percent, $token_score );
	}
}
