<?php
/**
 * Excel/XLSX importer with precise cell-reference reading and strict validation.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Import_Excel {
	private array $created_categories = array();

	/**
	 * Import a spreadsheet and create/update partners.
	 */
	public function import( array $file, array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'update_existing' => true,
				'publish'         => true,
			)
		);

		$this->validate_file( $file );
		$rows = $this->read_file( $file['tmp_name'], $file['name'] );

		if ( empty( $rows ) || count( $rows ) < 2 ) {
			wp_die( esc_html__( 'A planilha está vazia ou não possui cabeçalho.', 'serben-clube-beneficios' ) );
		}

		$prepared = $this->prepare_rows( $rows );
		$headers  = $prepared['headers'];
		$rows     = $prepared['rows'];
		$mode     = $this->detect_mode( $headers, $rows );

		$this->validate_headers( $mode, $headers );

		if ( 'report' === $mode ) {
			return $this->import_report( $headers, $rows, $args );
		}

		if ( 'mixed' === $mode ) {
			return $this->import_mixed( $headers, $rows, $args );
		}

		return 'online' === $mode ? $this->import_online( $headers, $rows, $args ) : $this->import_physical( $headers, $rows, $args );
	}

	private function validate_file( array $file ): void {
		if ( ! empty( $file['error'] ) ) {
			wp_die( esc_html__( 'Erro no upload do arquivo.', 'serben-clube-beneficios' ) );
		}

		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'xlsx', 'csv' ), true ) ) {
			wp_die( esc_html__( 'Formato inválido. Use .xlsx ou .csv.', 'serben-clube-beneficios' ) );
		}
	}

	private function read_file( string $path, string $name ): array {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'csv' === $ext ) {
			return $this->read_csv( $path );
		}

		// The SERBEN physical sheet contains semicolon-separated values inside cells.
		// Some CSV conversions flatten those values and shift columns. For .xlsx, prefer
		// the OpenXML cell references directly (A1, B1, C1...), preserving empty columns.
		if ( class_exists( 'ZipArchive' ) ) {
			return $this->read_xlsx_native( $path );
		}

		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return $this->read_xlsx_phpspreadsheet( $path );
		}

		wp_die( esc_html__( 'Não foi possível ler .xlsx. Instale as dependências via Composer ou habilite a extensão ZipArchive do PHP.', 'serben-clube-beneficios' ) );
	}

	private function read_xlsx_phpspreadsheet( string $path ): array {
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load( $path );
		$best_rows   = array();
		$best_score  = -1;

		foreach ( $spreadsheet->getWorksheetIterator() as $worksheet ) {
			$highest_row    = $worksheet->getHighestDataRow();
			$highest_column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString( $worksheet->getHighestDataColumn() );
			$rows           = array();

			for ( $r = 1; $r <= $highest_row; $r++ ) {
				$row = array();
				for ( $c = 1; $c <= $highest_column; $c++ ) {
					$row[] = $worksheet->getCellByColumnAndRow( $c, $r )->getFormattedValue();
				}
				$rows[] = $row;
			}

			$score = $this->best_header_score( $rows );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_rows  = $rows;
			}
		}

		return $best_rows;
	}

	private function read_csv( string $path ): array {
		$raw_lines = file( $path, FILE_IGNORE_NEW_LINES );
		if ( false === $raw_lines || empty( $raw_lines ) ) {
			return array();
		}

		$delimiters = array( ',', ';', "	" );
		$best       = array( 'score' => -1, 'delimiter' => ';', 'rows' => array() );

		foreach ( $delimiters as $delimiter ) {
			$rows = array();
			foreach ( $raw_lines as $line ) {
				$rows[] = str_getcsv( (string) $line, $delimiter );
			}
			$score = $this->best_header_score( $rows );
			if ( $score > $best['score'] ) {
				$best = array( 'score' => $score, 'delimiter' => $delimiter, 'rows' => $rows );
			}
		}

		return $best['rows'];
	}

	/**
	 * Lightweight XLSX reader used only when PhpSpreadsheet is unavailable.
	 * It preserves sparse columns using each cell reference, preventing column shifts.
	 */
	private function read_xlsx_native( string $path ): array {
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Instale as dependências do plugin via Composer ou habilite a extensão ZipArchive do PHP para ler .xlsx.', 'serben-clube-beneficios' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			wp_die( esc_html__( 'Não foi possível abrir a planilha.', 'serben-clube-beneficios' ) );
		}

		$shared = $this->read_shared_strings( $zip );

		$best_rows  = array();
		$best_score = -1;
		for ( $i = 1; $i <= 20; $i++ ) {
			$sheet_xml = $zip->getFromName( 'xl/worksheets/sheet' . $i . '.xml' );
			if ( false === $sheet_xml ) {
				continue;
			}
			$rows  = $this->parse_sheet_xml( $sheet_xml, $shared );
			$score = $this->best_header_score( $rows );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_rows  = $rows;
			}
		}

		$zip->close();

		if ( empty( $best_rows ) ) {
			wp_die( esc_html__( 'Nenhuma aba válida foi encontrada na planilha.', 'serben-clube-beneficios' ) );
		}

		return $best_rows;
	}

	private function read_shared_strings( ZipArchive $zip ): array {
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false === $shared_xml ) {
			return array();
		}

		$xml = simplexml_load_string( $shared_xml );
		if ( ! $xml ) {
			return array();
		}

		// Use namespace-independent XPath because XLSX generators may use either
		// a default namespace (<sst>) or a prefix (<x:sst>).
		$items  = $xml->xpath( '//*[local-name()="si"]' );
		$shared = array();
		foreach ( is_array( $items ) ? $items : array() as $si ) {
			$text_nodes = $si->xpath( './/*[local-name()="t"]' );
			$text = '';
			foreach ( is_array( $text_nodes ) ? $text_nodes : array() as $node ) {
				$text .= (string) $node;
			}
			$shared[] = $text;
		}

		return $shared;
	}

	private function parse_sheet_xml( string $sheet_xml, array $shared ): array {
		$xml = simplexml_load_string( $sheet_xml );
		if ( ! $xml ) {
			return array();
		}

		$row_nodes = $xml->xpath( '//*[local-name()="sheetData"]/*[local-name()="row"]' );
		if ( ! is_array( $row_nodes ) || empty( $row_nodes ) ) {
			return array();
		}

		$rows = array();
		foreach ( $row_nodes as $row ) {
			$out = array();
			$cells = $row->xpath( './*[local-name()="c"]' );
			foreach ( is_array( $cells ) ? $cells : array() as $cell ) {
				$attrs = $cell->attributes();
				$ref   = isset( $attrs['r'] ) ? (string) $attrs['r'] : 'A1';
				$col   = $this->column_index_from_ref( $ref );
				$type  = isset( $attrs['t'] ) ? (string) $attrs['t'] : '';
				$value = '';

				if ( 'inlineStr' === $type ) {
					$text_nodes = $cell->xpath( './/*[local-name()="is"]//*[local-name()="t"]' );
					foreach ( is_array( $text_nodes ) ? $text_nodes : array() as $node ) {
						$value .= (string) $node;
					}
				} else {
					$value_nodes = $cell->xpath( './*[local-name()="v"]' );
					if ( is_array( $value_nodes ) && isset( $value_nodes[0] ) ) {
						$raw = (string) $value_nodes[0];
						$value = 's' === $type && isset( $shared[ (int) $raw ] ) ? $shared[ (int) $raw ] : $raw;
					}
				}

				$out[ $col ] = $value;
			}

			if ( ! empty( $out ) ) {
				$max_col = max( array_keys( $out ) );
				$line    = array_fill( 0, $max_col + 1, '' );
				foreach ( $out as $col => $value ) {
					$line[ $col ] = $value;
				}
				$rows[] = $line;
			}
		}

		return $rows;
	}

	private function column_index_from_ref( string $ref ): int {
		preg_match( '/^[A-Z]+/i', $ref, $m );
		$letters = strtoupper( $m[0] ?? 'A' );
		$index   = 0;
		for ( $i = 0; $i < strlen( $letters ); $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return $index - 1;
	}

	private function prepare_rows( array $rows ): array {
		$best_index = null;
		$best_score = -1;

		$limit = min( 20, count( $rows ) );
		for ( $i = 0; $i < $limit; $i++ ) {
			if ( ! is_array( $rows[ $i ] ) || $this->row_is_empty( $rows[ $i ] ) ) {
				continue;
			}
			$headers = $this->normalize_headers( $rows[ $i ] );
			$score   = max( $this->header_score( $headers, 'physical' ), $this->header_score( $headers, 'online' ), $this->header_score( $headers, 'report' ) );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_index = $i;
			}
		}

		if ( null === $best_index || $best_score < 4 ) {
			wp_die( esc_html__( 'Não consegui reconhecer o cabeçalho da planilha. Aceitos: físico (Nome_Fantasia_Vitrine, Tipo_Unidade, Endereco, Cidade...), online (ID_Credenciado, Nome Fantasia, % Cash Bume...) ou relatório administrativo (ID, Nome Fantasia, Razão Social, Tipo, CPF/CNPJ, E-mail, Status). [Importador v1.9.3]', 'serben-clube-beneficios' ) );
		}

		$headers = $this->normalize_headers( $rows[ $best_index ] );
		$data    = array_slice( $rows, $best_index + 1 );
		$data    = array_values(
			array_filter(
				$data,
				function ( $row ) {
					return is_array( $row ) && ! $this->row_is_empty( $row );
				}
			)
		);

		return array(
			'headers' => $headers,
			'rows'    => $data,
		);
	}

	private function best_header_score( array $rows ): int {
		$best  = 0;
		$limit = min( 20, count( $rows ) );
		for ( $i = 0; $i < $limit; $i++ ) {
			if ( ! is_array( $rows[ $i ] ) || $this->row_is_empty( $rows[ $i ] ) ) {
				continue;
			}
			$headers = $this->normalize_headers( $rows[ $i ] );
			$best    = max( $best, $this->header_score( $headers, 'physical' ), $this->header_score( $headers, 'online' ), $this->header_score( $headers, 'report' ) );
		}
		return $best;
	}

	private function normalize_headers( array $headers ): array {
		$map = array();
		foreach ( $headers as $index => $header ) {
			$key = Serben_Utils::normalize_slug( (string) $header );
			if ( '' !== $key && ! isset( $map[ $key ] ) ) {
				$map[ $key ] = $index;
			}
		}

		// Compatibilidade com modelos físicos segmentados por tipo de parceiro.
		// Ex.: "Categoria - Viagem" e "Sub Categoria -Hospedagem".
		$canonical_aliases = array(
			'categoria'     => array( 'categoria-viagem' ),
			'sub-categoria' => array( 'sub-categoria-hospedagem' ),
		);

		foreach ( $canonical_aliases as $canonical => $aliases ) {
			if ( isset( $map[ $canonical ] ) ) {
				continue;
			}
			foreach ( $aliases as $alias ) {
				if ( isset( $map[ $alias ] ) ) {
					$map[ $canonical ] = $map[ $alias ];
					break;
				}
			}
		}

		return $map;
	}

	private function header_score( array $headers, string $mode ): int {
		if ( 'report' === $mode ) {
			$groups = array(
				array( 'id' ),
				array( 'nome-fantasia', 'nome' ),
				array( 'razao-social' ),
				array( 'tipo' ),
				array( 'cpf-cnpj', 'cnpj' ),
				array( 'e-mail', 'email' ),
				array( 'status' ),
			);
		} else {
			$groups = 'online' === $mode ? array(
			array( 'id-credenciado', 'id' ),
			array( 'nome-fantasia', 'nome', 'parceiro' ),
			array( 'cash-bume', 'desconto', 'cash-do-usuario-50' ),
			array( 'descricao-da-empresa', 'descricao' ),
			array( 'categoria-aplicada', 'categoria', 'categorias' ),
			array( 'regras-de-validacao', 'regras-de-validacao' ),
			array( 'link-do-logotipo', 'link-do-parceiro', 'link' ),
		) : array(
			array( 'nome-fantasia-vitrine', 'nome-fantasia', 'nome-da-empresa', 'empresa', 'nome', 'parceiro' ),
			array( 'tipo-unidade', 'tipo-da-unidade' ),
			array( 'cnpj' ),
			array( 'categoria' ),
			array( 'sub-categoria', 'subcategoria' ),
			array( 'cep-local', 'cep' ),
			array( 'endereco', 'endereco-local', 'endereco-da-unidade' ),
			array( 'numero', 'n' ),
			array( 'cidade' ),
			array( 'estado', 'uf' ),
			array( 'telefone-cliente', 'telefone' ),
			array( 'whatsapp' ),
			array( 'desconto-padrao-serben', 'desconto' ),
			);
		}

		$score = 0;
		foreach ( $groups as $group ) {
			if ( $this->has_header( $headers, $group ) ) {
				$score++;
			}
		}
		return $score;
	}

	private function detect_mode( array $headers, array $rows = array() ): string {
		// Administrative partner report: update only fields present in the report,
		// without changing physical/online type, categories, discounts or branches.
		if (
			$this->has_header( $headers, array( 'id' ) )
			&& $this->has_header( $headers, array( 'nome-fantasia' ) )
			&& $this->has_header( $headers, array( 'razao-social' ) )
			&& $this->has_header( $headers, array( 'cpf-cnpj', 'cnpj' ) )
			&& $this->has_header( $headers, array( 'e-mail', 'email' ) )
			&& $this->has_header( $headers, array( 'status' ) )
		) {
			return 'report';
		}

		// Unified exports may contain physical and online partners in the same file.
		// The explicit "Tipo" column identifies this layout; each row is then
		// classified by address, so only records with an address become physical.
		if (
			$this->has_header( $headers, array( 'tipo' ) )
			&& $this->has_header( $headers, array( 'nome-fantasia-vitrine', 'nome-fantasia', 'nome', 'parceiro' ) )
			&& $this->has_header( $headers, array( 'endereco', 'endereco-local', 'endereco-da-unidade' ) )
		) {
			return 'mixed';
		}

		$online_score   = $this->header_score( $headers, 'online' );
		$physical_score = $this->header_score( $headers, 'physical' );

		return $online_score >= 4 && $online_score >= $physical_score ? 'online' : 'physical';
	}

	private function validate_headers( string $mode, array $headers ): void {
		if ( 'report' === $mode ) {
			$required = array(
				'ID'           => array( 'id' ),
				'Nome Fantasia'=> array( 'nome-fantasia' ),
				'Razão Social' => array( 'razao-social' ),
				'CPF/CNPJ'     => array( 'cpf-cnpj', 'cnpj' ),
				'E-mail'       => array( 'e-mail', 'email' ),
				'Status'       => array( 'status' ),
			);
		} elseif ( 'mixed' === $mode ) {
			$required = array(
				'Nome_Fantasia_Vitrine' => array( 'nome-fantasia-vitrine', 'nome-fantasia', 'nome', 'parceiro' ),
				'Tipo'                  => array( 'tipo' ),
				'Categoria'             => array( 'categoria', 'categoria-aplicada', 'categorias' ),
				'Desconto'              => array( 'desconto', 'cash-bume', 'cash-do-usuario-50' ),
				'Endereco'              => array( 'endereco', 'endereco-local', 'endereco-da-unidade' ),
			);
		} elseif ( 'online' === $mode ) {
			$required = array(
				'Nome Fantasia'        => array( 'nome-fantasia', 'nome-fantasia-vitrine', 'nome', 'parceiro' ),
				'Categoria Aplicada'   => array( 'categoria-aplicada', 'categoria', 'categorias' ),
				'% Cash Bume/Desconto' => array( 'cash-bume', 'desconto', 'cash-do-usuario-50' ),
			);
		} else {
			$required = array(
				'Nome_Fantasia_Vitrine' => array( 'nome-fantasia-vitrine', 'nome-fantasia', 'nome-da-empresa', 'empresa', 'nome', 'parceiro' ),
				'Tipo_Unidade'          => array( 'tipo-unidade', 'tipo-da-unidade' ),
				'Categoria'             => array( 'categoria', 'categorias' ),
				'Endereco'              => array( 'endereco', 'endereco-local', 'endereco-da-unidade' ),
				'Cidade'                => array( 'cidade' ),
				'Estado'                => array( 'estado', 'uf' ),
			);
		}

		$missing = array();
		foreach ( $required as $label => $aliases ) {
			if ( ! $this->has_header( $headers, $aliases ) ) {
				$missing[] = $label;
			}
		}

		if ( $missing ) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: missing columns */
						__( 'A planilha foi reconhecida como modelo %1$s, mas faltam colunas obrigatórias: %2$s. Importação cancelada para evitar dados puxados em campos errados.', 'serben-clube-beneficios' ),
						'mixed' === $mode ? 'misto (físico/online)' : ( 'online' === $mode ? 'online' : 'físico' ),
						implode( ', ', $missing )
					)
				)
			);
		}
	}

	private function has_header( array $headers, array $aliases ): bool {
		foreach ( $aliases as $alias ) {
			if ( isset( $headers[ $alias ] ) ) {
				return true;
			}
		}
		return false;
	}

	private function row_is_empty( array $row ): bool {
		foreach ( $row as $value ) {
			if ( '' !== trim( (string) $value ) ) {
				return false;
			}
		}
		return true;
	}

	private function cell( array $headers, array $row, array $names ): string {
		foreach ( $names as $name ) {
			$key = Serben_Utils::normalize_slug( $name );
			if ( isset( $headers[ $key ] ) ) {
				$index = $headers[ $key ];
				return isset( $row[ $index ] ) ? Serben_Utils::clean_text( (string) $row[ $index ] ) : '';
			}
		}
		return '';
	}

	/**
	 * Import an administrative partner report safely.
	 * Only fields present in this report are updated; existing benefit data remains intact.
	 */
	private function import_report( array $headers, array $rows, array $args ): array {
		$result = array( 'created' => 0, 'updated' => 0, 'branches' => 0, 'categories' => 0 );

		foreach ( $rows as $row ) {
			$name = $this->cell( $headers, $row, array( 'Nome Fantasia', 'Nome' ) );
			if ( '' === $name || $this->looks_like_misaligned_partner_name( $name ) ) {
				continue;
			}

			$external_id = $this->cell( $headers, $row, array( 'ID' ) );
			$document    = $this->cell( $headers, $row, array( 'CPF/CNPJ', 'CNPJ' ) );
			$post_id     = $this->find_existing_partner( $name, $external_id, $args['update_existing'], $document );

			$status_text = strtolower( remove_accents( $this->cell( $headers, $row, array( 'Status' ) ) ) );
			$post_status = false !== strpos( $status_text, 'inativ' ) ? 'draft' : ( $args['publish'] ? 'publish' : 'draft' );
			$postarr = array(
				'post_type'   => SERBEN_POST_TYPE,
				'post_status' => $post_status,
				'post_title'  => sanitize_text_field( $name ),
				'post_name'   => sanitize_title( $name ),
			);

			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				wp_update_post( $postarr );
				$result['updated']++;
			} else {
				$post_id = wp_insert_post( $postarr, true );
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				$result['created']++;
			}

			update_post_meta( $post_id, SERBEN_META_PREFIX . 'external_id', sanitize_text_field( $external_id ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'razao_social', sanitize_text_field( $this->cell( $headers, $row, array( 'Razão Social', 'Razao Social' ) ) ) );
			if ( '' !== $document ) {
				update_post_meta( $post_id, SERBEN_META_PREFIX . 'cnpj', sanitize_text_field( $document ) );
			}
			$email = $this->cell( $headers, $row, array( 'E-mail', 'Email' ) );
			if ( '' !== $email ) {
				update_post_meta( $post_id, SERBEN_META_PREFIX . 'email', sanitize_email( $email ) );
			}
		}

		Serben_Utils::clear_query_cache();
		return $result;
	}

	/**
	 * Import a unified spreadsheet containing physical and online partners.
	 * Business rule: an address makes the row physical; without an address it is online.
	 */
	private function import_mixed( array $headers, array $rows, array $args ): array {
		$physical_rows = array();
		$online_rows   = array();

		foreach ( $rows as $row ) {
			$name = $this->cell( $headers, $row, array( 'Nome_Fantasia_Vitrine', 'Nome Fantasia Vitrine', 'Nome Fantasia', 'Nome', 'Parceiro' ) );
			if ( '' === $name || $this->looks_like_misaligned_partner_name( $name ) ) {
				continue;
			}

			$address = $this->build_address( $headers, $row );
			if ( '' !== trim( $address ) ) {
				$physical_rows[] = $row;
			} else {
				$online_rows[] = $row;
			}
		}

		$result = array( 'created' => 0, 'updated' => 0, 'branches' => 0, 'categories' => 0 );
		$parts  = array();

		if ( $physical_rows ) {
			$parts[] = $this->import_physical( $headers, $physical_rows, $args );
		}
		if ( $online_rows ) {
			$parts[] = $this->import_online( $headers, $online_rows, $args );
		}

		foreach ( $parts as $part ) {
			foreach ( $result as $key => $value ) {
				$result[ $key ] += isset( $part[ $key ] ) ? (int) $part[ $key ] : 0;
			}
		}

		return $result;
	}

	private function import_physical( array $headers, array $rows, array $args ): array {
		$this->validate_physical_data_alignment( $headers, $rows );

		$groups = array();
		foreach ( $rows as $row ) {
			$name = $this->cell( $headers, $row, array( 'Nome_Fantasia_Vitrine', 'Nome Fantasia Vitrine', 'Nome Fantasia', 'Nome da empresa', 'Empresa', 'Nome', 'Parceiro' ) );
			if ( '' === $name || $this->looks_like_misaligned_partner_name( $name ) ) {
				continue;
			}

			$key = $this->physical_group_key( $headers, $row, $name );
			$groups[ $key ][] = $row;
		}

		$result = array( 'created' => 0, 'updated' => 0, 'branches' => 0, 'categories' => 0 );

		foreach ( $groups as $group_rows ) {
			$main = $this->find_main_row( $headers, $group_rows );
			$name = $this->cell( $headers, $main, array( 'Nome_Fantasia_Vitrine', 'Nome Fantasia Vitrine', 'Nome Fantasia', 'Nome da empresa', 'Empresa', 'Nome', 'Parceiro', 'Razao_Social', 'Razão Social' ) );
			if ( '' === $name || $this->looks_like_misaligned_partner_name( $name ) ) {
				continue;
			}

			$cnpj    = $this->cell( $headers, $main, array( 'CNPJ' ) );
			$post_id = $this->find_existing_partner( $name, $this->cell( $headers, $main, array( 'ID_Estabelecimento', 'ID Estabelecimento', 'ID' ) ), $args['update_existing'], $cnpj );
			$status  = $args['publish'] ? 'publish' : 'draft';
			$content = $this->cell( $headers, $main, array( 'Descrição da Empresa', 'Descricao da Empresa', 'Descrição', 'Descricao' ) );
			$postarr = array(
				'post_type'    => SERBEN_POST_TYPE,
				'post_status'  => $status,
				'post_title'   => sanitize_text_field( $name ),
				'post_name'    => sanitize_title( $name ),
				'post_content' => wp_kses_post( $content ),
			);

			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				wp_update_post( $postarr );
				$result['updated']++;
			} else {
				$post_id = wp_insert_post( $postarr, true );
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				$result['created']++;
			}

			$desconto   = $this->cell( $headers, $main, array( 'Desconto_Padrao_SERBEN', 'Desconto Padrao SERBEN', 'Desconto' ) );
			$regras     = $this->cell( $headers, $main, array( 'Descrição da Promoção', 'Descricao da Promocao', 'Discrição da Promoção', 'Discricao da Promocao', 'Regras do benefício', 'Regras do beneficio' ) );
			$atividades = $this->cell( $headers, $main, array( 'Sub Categoria', 'Sub_Categoria', 'Subcategoria', 'Atividades', 'Modalidades' ) );

			update_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', 'fisico' );
			delete_post_meta( $post_id, SERBEN_META_PREFIX . 'desconto_online' );
			delete_post_meta( $post_id, SERBEN_META_PREFIX . 'link_parceiro' );
			delete_post_meta( $post_id, SERBEN_META_PREFIX . 'advertiser_html' );
			delete_post_meta( $post_id, SERBEN_META_PREFIX . 'como_utilizar' );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'external_id', sanitize_text_field( $this->cell( $headers, $main, array( 'ID_Estabelecimento', 'ID Estabelecimento', 'ID' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'cnpj', sanitize_text_field( $cnpj ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'razao_social', sanitize_text_field( $this->cell( $headers, $main, array( 'Razao_Social', 'Razão Social' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'descricao', sanitize_textarea_field( $content ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'atividades', sanitize_textarea_field( $atividades ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'desconto', sanitize_text_field( $desconto ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'regras', sanitize_textarea_field( $regras ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'site', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $main, array( 'site', 'Site', 'Site/Link' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'whatsapp', $this->sanitize_import_phone( $this->cell( $headers, $main, array( 'whatsapp', 'WhatsApp' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'email', sanitize_email( $this->cell( $headers, $main, array( 'E-mail Cliente', 'Email Cliente', 'Email ADM', 'E-mail ADM' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'instagram', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $main, array( 'Rede social Instagram', 'Instagram' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'facebook', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $main, array( 'Rede Social Facebook', 'Facebook' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'youtube', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $main, array( 'youtube', 'YouTube' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'tiktok', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $main, array( 'TikTok' ) ) ) );

			$result['categories'] += $this->assign_categories( $post_id, $this->cell( $headers, $main, array( 'Categoria', 'Categoria ', 'Categoria Aplicada', 'Categorias' ) ) );

			$branches = array();
			foreach ( $group_rows as $row ) {
				$branch = $this->physical_branch_from_row( $headers, $row );
				if ( '' === trim( $branch['unit_name'] . $branch['address'] . $branch['city'] . $branch['phone'] . $branch['whatsapp'] ) ) {
					continue;
				}
				$branches[] = $branch;
			}

			Serben_Branches::save_for_post( $post_id, $branches );
			$result['branches'] += count( $branches );
		}

		Serben_Utils::clear_query_cache();
		return $result;
	}

	private function physical_group_key( array $headers, array $row, string $fallback_name ): string {
		$cnpj = preg_replace( '/\D+/', '', $this->cell( $headers, $row, array( 'CNPJ' ) ) );
		if ( '' !== $cnpj ) {
			return 'cnpj:' . $cnpj;
		}

		$razao = Serben_Utils::normalize_string( $this->cell( $headers, $row, array( 'Razao_Social', 'Razão Social' ) ) );
		if ( '' !== $razao ) {
			return 'razao:' . $razao;
		}

		return 'nome:' . Serben_Utils::normalize_string( $fallback_name );
	}

	private function physical_branch_from_row( array $headers, array $row ): array {
		$address = $this->build_address( $headers, $row );
		$branch  = array(
			'unit_name'   => $this->cell( $headers, $row, array( 'Nome da unidade', 'Nome_Unidade', 'Unidade', 'Nome_Fantasia_Vitrine', 'Nome Fantasia Vitrine', 'Nome Fantasia', 'Nome', 'Parceiro' ) ),
			'address'     => $address,
			'city'        => $this->cell( $headers, $row, array( 'Cidade' ) ),
			'state'       => $this->cell( $headers, $row, array( 'Estado', 'UF' ) ),
			'zipcode'     => $this->cell( $headers, $row, array( 'CEP_Local', 'CEP Local', 'CEP' ) ),
			'phone'       => $this->sanitize_import_phone( $this->cell( $headers, $row, array( 'Telefone cliente', 'Telefone Cliente', 'Telefone' ) ) ),
			'whatsapp'    => $this->sanitize_import_phone( $this->cell( $headers, $row, array( 'whatsapp', 'WhatsApp' ) ) ),
			'hours'       => $this->cell( $headers, $row, array( 'Horário de funcionamento', 'Horario de funcionamento', 'Horário', 'Horario' ) ),
			'maps_url'    => Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $row, array( 'Link do Google Maps', 'Google Maps', 'Maps' ) ) ),
			'responsible' => $this->cell( $headers, $row, array( 'Responsável', 'Responsavel' ) ),
		);

		if ( '' === $branch['maps_url'] && '' !== $address ) {
			$branch['maps_url'] = $this->build_google_maps_search_url( $branch );
		}

		return $branch;
	}

	private function build_address( array $headers, array $row ): string {
		$street   = $this->cell( $headers, $row, array( 'Endereco', 'Endereço', 'Endereco Local', 'Endereço Local' ) );
		$number   = $this->cell( $headers, $row, array( 'Numero', 'Numero ', 'Número', 'Nº', 'N' ) );
		$district = $this->cell( $headers, $row, array( 'Bairro' ) );

		$address = $street;
		if ( '' !== $number ) {
			$address .= '' !== $address ? ', ' . $number : $number;
		}
		if ( '' !== $district ) {
			$address .= '' !== $address ? ' - ' . $district : $district;
		}

		return trim( $address );
	}

	private function build_google_maps_search_url( array $branch ): string {
		$parts = array_filter(
			array(
				$branch['address'] ?? '',
				$branch['city'] ?? '',
				$branch['state'] ?? '',
				$branch['zipcode'] ?? '',
				'Brasil',
			)
		);

		return esc_url_raw( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( implode( ', ', $parts ) ) );
	}

	private function looks_like_misaligned_partner_name( string $name ): bool {
		$normalized = Serben_Utils::normalize_slug( $name );
		$blocked    = array(
			'free', 'ativo', 'inativo', 'matriz', 'filial', 'brasil', 'sao-paulo',
			'academia', 'musculacao', 'treinamento-funcional', 'natacao', 'hidroginastica',
			'spinning', 'bike-indoor', 'fitdance', 'fit-dance', 'fit-dance', 'body-pump',
			'body-combat', 'power-jump', 'gap', 'ginastica-localizada', 'alongamento',
			'yoga', 'abdominal', 'pilates', 'muay-thai', 'jiu-jitsu', 'kick-boxing',
			'danca-de-salao', 'ritmos', 'jump', 'step', 'balance', 'core', 'boxe', 'zumba'
		);
		if ( in_array( $normalized, $blocked, true ) ) {
			return true;
		}

		// Values like a long semicolon-separated activities list are not partner names.
		return substr_count( $name, ';' ) >= 3;
	}

	private function validate_physical_data_alignment( array $headers, array $rows ): void {
		$suspicious = array();
		$checked    = 0;

		foreach ( $rows as $index => $row ) {
			$name = $this->cell( $headers, $row, array( 'Nome_Fantasia_Vitrine', 'Nome Fantasia Vitrine', 'Nome Fantasia', 'Nome da empresa', 'Empresa', 'Nome', 'Parceiro' ) );
			if ( '' === $name ) {
				continue;
			}

			$checked++;
			$reasons = array();
			$type    = Serben_Utils::normalize_slug( $this->cell( $headers, $row, array( 'Tipo_Unidade', 'Tipo Unidade', 'Tipo da unidade' ) ) );
			$street  = $this->cell( $headers, $row, array( 'Endereco', 'Endereço', 'Endereco Local', 'Endereço Local' ) );
			$city    = $this->cell( $headers, $row, array( 'Cidade' ) );
			$state   = $this->cell( $headers, $row, array( 'Estado', 'UF' ) );
			$cnpj    = preg_replace( '/\D+/', '', $this->cell( $headers, $row, array( 'CNPJ' ) ) );
			$phone   = preg_replace( '/\D+/', '', $this->cell( $headers, $row, array( 'Telefone cliente', 'Telefone Cliente', 'Telefone' ) ) );

			if ( $this->looks_like_misaligned_partner_name( $name ) ) {
				$reasons[] = 'nome parece modalidade/subcategoria';
			}
			if ( '' !== $type && ! in_array( $type, array( 'matriz', 'filial' ), true ) ) {
				$reasons[] = 'Tipo_Unidade não é Matriz/Filial';
			}
			if ( '' !== $street && $this->looks_like_activity_value( $street ) && ! $this->looks_like_street_address( $street ) ) {
				$reasons[] = 'Endereco parece lista de modalidades';
			}
			if ( '' !== $cnpj && 14 !== strlen( $cnpj ) ) {
				$reasons[] = 'CNPJ inválido/deslocado';
			}
			if ( strlen( $phone ) > 13 ) {
				$reasons[] = 'Telefone cliente contém dígitos demais';
			}
			if ( 'brasil' === Serben_Utils::normalize_slug( $state ) || 'brasil' === Serben_Utils::normalize_slug( $city ) ) {
				$reasons[] = 'Cidade/Estado recebeu País';
			}

			if ( $reasons ) {
				$suspicious[] = sprintf(
					'linha %d: "%s" (%s)',
					$index + 2,
					Serben_Utils::clean_text( $name ),
					implode( '; ', $reasons )
				);
			}
		}

		if ( $checked > 0 && $suspicious ) {
			$sample = implode( ' | ', array_slice( $suspicious, 0, 6 ) );
			wp_die(
				esc_html(
					sprintf(
						__( 'Importação cancelada: a planilha está com colunas deslocadas antes de criar os parceiros. Isso costuma acontecer quando um CSV usa ponto e vírgula como separador e também possui ponto e vírgula dentro de “Sub Categoria”. Envie o arquivo .xlsx original ou um CSV com campos entre aspas. Exemplos detectados: %s', 'serben-clube-beneficios' ),
						$sample
					)
				)
			);
		}
	}

	private function looks_like_street_address( string $value ): bool {
		$normalized = Serben_Utils::normalize_string( $value );
		return (bool) preg_match( '/\b(rua|r|avenida|av|alameda|travessa|estrada|rodovia|praça|praca|largo|via)\b/i', $normalized );
	}

	private function looks_like_activity_value( string $value ): bool {
		$normalized = Serben_Utils::normalize_slug( $value );
		$tokens     = array(
			'academia', 'musculacao', 'treinamento-funcional', 'natacao', 'hidroginastica', 'spinning', 'bike-indoor',
			'fitdance', 'fit-dance', 'body-pump', 'body-combat', 'power-jump', 'gap', 'ginastica-localizada',
			'alongamento', 'yoga', 'abdominal', 'pilates', 'muay-thai', 'jiu-jitsu', 'kick-boxing', 'danca-de-salao',
			'ritmos', 'jump', 'step', 'balance', 'core', 'boxe', 'zumba', 'meditacao', 'hiit', 'circuito-funcional'
		);
		foreach ( $tokens as $token ) {
			if ( $normalized === $token || false !== strpos( $normalized, $token ) ) {
				return true;
			}
		}
		return false;
	}

	private function sanitize_import_phone( string $value ): string {
		$value  = Serben_Utils::sanitize_phone( $value );
		$digits = preg_replace( '/\D+/', '', $value );

		// Brazilian fixed/mobile phones normally have 10-11 digits without country code,
		// or 12-13 with country code 55. Longer strings are almost always shifted rows.
		if ( strlen( $digits ) > 13 ) {
			return '';
		}

		return $value;
	}

	private function import_online( array $headers, array $rows, array $args ): array {
		$result = array( 'created' => 0, 'updated' => 0, 'branches' => 0, 'categories' => 0 );

		foreach ( $rows as $row ) {
			$name = $this->cell( $headers, $row, array( 'Nome Fantasia', 'Nome_Fantasia_Vitrine', 'Nome Fantasia Vitrine', 'Nome', 'Parceiro' ) );
			if ( '' === $name || $this->looks_like_misaligned_partner_name( $name ) ) {
				continue;
			}

			$external_id = $this->cell( $headers, $row, array( 'ID_Credenciado', 'ID Credenciado', 'ID' ) );
			$post_id     = $this->find_existing_partner( $name, $external_id, $args['update_existing'] );
			$content     = $this->cell( $headers, $row, array( 'Descrição da Empresa', 'Descricao da Empresa', 'Descrição', 'Descricao' ) );
			$status      = $args['publish'] ? 'publish' : 'draft';
			$postarr     = array(
				'post_type'    => SERBEN_POST_TYPE,
				'post_status'  => $status,
				'post_title'   => sanitize_text_field( $name ),
				'post_name'    => sanitize_title( $name ),
				'post_content' => wp_kses_post( $content ),
			);

			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				wp_update_post( $postarr );
				$result['updated']++;
			} else {
				$post_id = wp_insert_post( $postarr, true );
				if ( is_wp_error( $post_id ) ) {
					continue;
				}
				$result['created']++;
			}

			$cash = $this->cell( $headers, $row, array( '% Cash Bume', 'Cash Bume', '% Cash do Usuário (50%)', 'Desconto' ) );
			if ( is_numeric( str_replace( ',', '.', $cash ) ) && (float) str_replace( ',', '.', $cash ) < 1 ) {
				$cash = number_format_i18n( (float) str_replace( ',', '.', $cash ) * 100, 2 ) . '% Cash Bume';
			}

			$link_html = $this->cell( $headers, $row, array( 'Link do Logotipo', 'Link do parceiro', 'Link Parceiro', 'Site/Link', 'Site', 'Link' ) );

			update_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', 'online' );
			Serben_Branches::save_for_post( $post_id, array() );
			delete_post_meta( $post_id, SERBEN_META_PREFIX . 'desconto' );
			delete_post_meta( $post_id, SERBEN_META_PREFIX . 'atividades' );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'external_id', sanitize_text_field( $external_id ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'descricao', sanitize_textarea_field( $content ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'desconto_online', sanitize_text_field( $cash ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'regras', sanitize_textarea_field( $this->cell( $headers, $row, array( 'Regras de Validação', 'Regras de Validacao', 'Regras' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'como_utilizar', sanitize_textarea_field( $this->cell( $headers, $row, array( 'Regra de Recebimento Cash', 'Como utilizar', 'Como Utilizar' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'link_parceiro', Serben_Utils::maybe_extract_href( $link_html ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'advertiser_html', wp_kses( $link_html, Serben_Utils::allowed_embed_html() ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'cupom', sanitize_text_field( $this->cell( $headers, $row, array( 'Cupom' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'validade', sanitize_text_field( $this->cell( $headers, $row, array( 'Validade' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'whatsapp', $this->sanitize_import_phone( $this->cell( $headers, $row, array( 'WhatsApp', 'whatsapp' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'instagram', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $row, array( 'Instagram' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'facebook', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $row, array( 'Facebook' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'youtube', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $row, array( 'YouTube', 'Youtube' ) ) ) );
			update_post_meta( $post_id, SERBEN_META_PREFIX . 'tiktok', Serben_Utils::sanitize_url_or_empty( $this->cell( $headers, $row, array( 'TikTok' ) ) ) );

			$result['categories'] += $this->assign_categories( $post_id, $this->cell( $headers, $row, array( 'Categoria Aplicada', 'Categoria', 'Categorias' ) ) );
		}

		Serben_Utils::clear_query_cache();
		return $result;
	}

	private function find_main_row( array $headers, array $rows ): array {
		foreach ( $rows as $row ) {
			$type = strtolower( remove_accents( $this->cell( $headers, $row, array( 'Tipo_Unidade', 'Tipo Unidade', 'Tipo da unidade' ) ) ) );
			if ( false !== strpos( $type, 'matriz' ) ) {
				return $row;
			}
		}
		return $rows[0];
	}

	private function find_existing_partner( string $name, string $external, bool $update_existing, string $cnpj = '' ): int {
		if ( ! $update_existing ) {
			return 0;
		}

		$lookups = array();
		$external = trim( $external );
		if ( '' !== $external ) {
			$lookups[ SERBEN_META_PREFIX . 'external_id' ] = $external;
		}
		$cnpj_digits = preg_replace( '/\D+/', '', $cnpj );
		if ( '' !== $cnpj_digits ) {
			$lookups[ SERBEN_META_PREFIX . 'cnpj' ] = $cnpj;
		}

		foreach ( $lookups as $meta_key => $value ) {
			$q = new WP_Query(
				array(
					'post_type'      => SERBEN_POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => $meta_key,
							'value' => $value,
						),
					),
				)
			);
			if ( ! empty( $q->posts ) ) {
				return absint( $q->posts[0] );
			}
		}

		$page = get_page_by_title( $name, OBJECT, SERBEN_POST_TYPE );
		return $page instanceof WP_Post ? absint( $page->ID ) : 0;
	}

	private function assign_categories( int $post_id, string $categories ): int {
		$names    = Serben_Utils::split_terms( $categories );
		$term_ids = array();
		$created  = 0;

		foreach ( $names as $name ) {
			$term = term_exists( $name, SERBEN_TAXONOMY );
			if ( ! $term ) {
				$term = wp_insert_term( $name, SERBEN_TAXONOMY, array( 'slug' => sanitize_title( $name ) ) );
				if ( ! is_wp_error( $term ) ) {
					$created++;
				}
			}
			if ( ! is_wp_error( $term ) ) {
				$term_ids[] = is_array( $term ) ? absint( $term['term_id'] ) : absint( $term );
			}
		}

		if ( $term_ids ) {
			wp_set_object_terms( $post_id, $term_ids, SERBEN_TAXONOMY, false );
		}

		return $created;
	}
}
