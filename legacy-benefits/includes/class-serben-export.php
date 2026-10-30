<?php
/**
 * Partner export.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Export {
	public function init(): void {
		add_action( 'admin_post_serben_export_excel', array( $this, 'export' ) );
	}

	public function export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		check_admin_referer( 'serben_export_excel' );

		$rows = $this->rows();
		if ( class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
			$this->export_xlsx( $rows );
			return;
		}
		$this->export_csv( $rows );
	}

	private function rows(): array {
		$header = array( 'ID', 'Nome', 'Tipo', 'Categoria', 'Subcategoria', 'Desconto', 'Site/Link', 'WhatsApp', 'Cidade', 'Estado', 'Unidade', 'Endereço', 'Telefone', 'Instagram', 'Facebook', 'YouTube', 'TikTok', 'Cupom', 'Validade', 'Visualizações', 'Cliques WhatsApp', 'Resgates' );
		$rows   = array( $header );
		$q      = new WP_Query(
			array(
				'post_type'      => SERBEN_POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $q->posts as $post ) {
			$tipo     = (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'tipo', true );
			$discount    = (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . ( 'online' === $tipo ? 'desconto_online' : 'desconto' ), true );
			$link        = (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . ( 'online' === $tipo ? 'link_parceiro' : 'site' ), true );
			$subcategory = 'online' === $tipo ? '' : (string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'atividades', true );
			$terms    = get_the_terms( $post->ID, SERBEN_TAXONOMY );
			$cats     = is_wp_error( $terms ) || empty( $terms ) ? '' : implode( '; ', wp_list_pluck( $terms, 'name' ) );
			$branches = Serben_Branches::get_for_post( $post->ID );
			if ( empty( $branches ) ) {
				$branches = array( array() );
			}
			foreach ( $branches as $branch ) {
				$rows[] = array(
					$post->ID,
					get_the_title( $post ),
					$tipo,
					$cats,
					$subcategory,
					$discount,
					$link,
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'whatsapp', true ),
					$branch['city'] ?? '',
					$branch['state'] ?? '',
					$branch['unit_name'] ?? '',
					$branch['address'] ?? '',
					$branch['phone'] ?? '',
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'instagram', true ),
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'facebook', true ),
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'youtube', true ),
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'tiktok', true ),
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'cupom', true ),
					(string) get_post_meta( $post->ID, SERBEN_META_PREFIX . 'validade', true ),
					absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'views', true ) ),
					absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'whatsapp_clicks', true ) ),
					absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'resgate_clicks', true ) ),
				);
			}
		}
		return $rows;
	}

	private function export_csv( array $rows ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="serben-parceiros.csv"' );
		echo "\xEF\xBB\xBF";
		$out = fopen( 'php://output', 'w' );
		foreach ( $rows as $row ) {
			fputcsv( $out, $row, ';' );
		}
		fclose( $out );
		exit;
	}

	private function export_xlsx( array $rows ): void {
		$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();
		$sheet->fromArray( $rows, null, 'A1' );
		$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="serben-parceiros.xlsx"' );
		$writer->save( 'php://output' );
		exit;
	}
}
