<?php
/**
 * Admin pages.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Admin {
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_serben_import_excel', array( $this, 'handle_import_excel' ) );
		add_action( 'admin_post_serben_import_logos', array( $this, 'handle_import_logos' ) );
		// Exclusao em massa desativada na fusao para proteger dados existentes.
		add_action( 'admin_post_serben_save_club_benefits', array( $this, 'handle_save_club_benefits' ) );
		add_filter( 'manage_' . SERBEN_POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . SERBEN_POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . SERBEN_POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . SERBEN_POST_TYPE,
			__( 'Importar Excel', 'serben-clube-beneficios' ),
			__( 'Importar Excel', 'serben-clube-beneficios' ),
			'manage_options',
			'serben-import-excel',
			array( $this, 'render_import_excel' )
		);

		add_submenu_page(
			'edit.php?post_type=' . SERBEN_POST_TYPE,
			__( 'Importar Logos', 'serben-clube-beneficios' ),
			__( 'Importar Logos', 'serben-clube-beneficios' ),
			'manage_options',
			'serben-import-logos',
			array( $this, 'render_import_logos' )
		);

		add_submenu_page(
			'edit.php?post_type=' . SERBEN_POST_TYPE,
			__( 'Exportar', 'serben-clube-beneficios' ),
			__( 'Exportar Excel', 'serben-clube-beneficios' ),
			'manage_options',
			'serben-export',
			array( $this, 'render_export' )
		);

		add_submenu_page(
			'edit.php?post_type=' . SERBEN_POST_TYPE,
			__( 'Manutenção', 'serben-clube-beneficios' ),
			__( 'Manutenção', 'serben-clube-beneficios' ),
			'manage_options',
			'serben-maintenance',
			array( $this, 'render_maintenance' )
		);

	}

	private function admin_notice_from_query(): void {
		if ( empty( $_GET['serben_notice'] ) ) {
			return;
		}
		$notice = sanitize_textarea_field( wp_unslash( $_GET['serben_notice'] ) );
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notice ) );
	}

	public function render_import_excel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		$this->admin_notice_from_query();
		?>
		<div class="wrap serben-admin-page">
			<h1><?php esc_html_e( 'Importar Excel', 'serben-clube-beneficios' ); ?></h1>
			<p><?php esc_html_e( 'Envie uma planilha .xlsx ou .csv. Arquivos mistos com a coluna “Tipo” são importados linha por linha: somente registros com Endereço são físicos; registros sem Endereço são online.', 'serben-clube-beneficios' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="serben-card">
				<?php wp_nonce_field( 'serben_import_excel', 'serben_import_excel_nonce' ); ?>
				<input type="hidden" name="action" value="serben_import_excel">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="serben_excel_file"><?php esc_html_e( 'Arquivo', 'serben-clube-beneficios' ); ?></label></th>
						<td><input type="file" id="serben_excel_file" name="serben_excel_file" accept=".xlsx,.csv" required></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Atualização', 'serben-clube-beneficios' ); ?></th>
						<td><label><input type="checkbox" name="update_existing" value="1"> <?php esc_html_e( 'Atualizar parceiros existentes por nome/CNPJ/ID quando encontrados', 'serben-clube-beneficios' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Publicação', 'serben-clube-beneficios' ); ?></th>
						<td><label><input type="checkbox" name="publish" value="1" checked> <?php esc_html_e( 'Publicar parceiros importados', 'serben-clube-beneficios' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Importar planilha', 'serben-clube-beneficios' ) ); ?>
			</form>
			<div class="serben-card">
				<h2><?php esc_html_e( 'Antes de reimportar', 'serben-clube-beneficios' ); ?></h2>
				<p><?php printf( wp_kses_post( __( 'Se você já importou uma planilha que gerou parceiros como “Free”, “Brasil”, “Matriz” ou números de WhatsApp gigantes, apague os registros em <a href="%s">Manutenção</a> e importe novamente.', 'serben-clube-beneficios' ) ), esc_url( admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-maintenance' ) ) ); ?></p>
			</div>
			<div class="serben-card">
				<h2><?php esc_html_e( 'Mapeamento reconhecido', 'serben-clube-beneficios' ); ?></h2>
				<p><strong><?php esc_html_e( 'Físico:', 'serben-clube-beneficios' ); ?></strong> Tipo_Unidade, Nome_Fantasia_Vitrine, CNPJ, CEP_Local, Endereco, Cidade, Estado, Telefone cliente, whatsapp, Descrição da Empresa, Desconto_Padrao_SERBEN, Descrição da Promoção, site, Instagram, Facebook, YouTube, TikTok.</p>
				<p><strong><?php esc_html_e( 'Online:', 'serben-clube-beneficios' ); ?></strong> ID_Credenciado, Nome Fantasia, % Cash Bume, Descrição da Empresa, Categoria Aplicada, Regras de Validação, Link do Logotipo.</p>
				<p><strong><?php esc_html_e( 'Planilha mista:', 'serben-clube-beneficios' ); ?></strong> ID, Nome_Fantasia_Vitrine, Tipo, Tipo_Unidade, Categoria, Desconto, Site/Link, Cidade, Estado e Endereço. A regra aplicada é: com Endereço = físico; sem Endereço = online.</p>
			</div>
		</div>
		<?php
	}

	public function render_import_logos(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		$this->admin_notice_from_query();
		?>
		<div class="wrap serben-admin-page">
			<h1><?php esc_html_e( 'Importar Logos por ZIP', 'serben-clube-beneficios' ); ?></h1>
			<p><?php esc_html_e( 'Envie um ZIP com imagens. O plugin compara o nome do arquivo e o nome da pasta com os parceiros cadastrados e vincula o melhor resultado.', 'serben-clube-beneficios' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="serben-card">
				<?php wp_nonce_field( 'serben_import_logos', 'serben_import_logos_nonce' ); ?>
				<input type="hidden" name="action" value="serben_import_logos">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="serben_logos_zip"><?php esc_html_e( 'Arquivo ZIP', 'serben-clube-beneficios' ); ?></label></th>
						<td><input type="file" id="serben_logos_zip" name="serben_logos_zip" accept=".zip" required></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Substituir logos existentes', 'serben-clube-beneficios' ); ?></th>
						<td><label><input type="checkbox" name="replace_existing" value="1"> <?php esc_html_e( 'Substituir logos já vinculados', 'serben-clube-beneficios' ); ?></label></td>
					</tr>
				</table>
				<?php submit_button( __( 'Importar logos', 'serben-clube-beneficios' ) ); ?>
			</form>
		</div>
		<?php
	}



	public function render_club_benefits(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		$this->admin_notice_from_query();
		$settings = class_exists( 'Serben_Utils' ) ? Serben_Utils::club_benefits() : array();
		?>
		<div class="wrap serben-admin-page">
			<h1><?php esc_html_e( 'Benefícios institucionais do Club Serben', 'serben-clube-beneficios' ); ?></h1>
			<p><?php esc_html_e( 'Esses benefícios aparecem de forma institucional nas páginas individuais dos parceiros. Ajuste os textos conforme a proposta comercial vigente.', 'serben-clube-beneficios' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="serben-card" style="max-width:980px">
				<?php wp_nonce_field( 'serben_save_club_benefits', 'serben_club_benefits_nonce' ); ?>
				<input type="hidden" name="action" value="serben_save_club_benefits">
				<table class="form-table" role="presentation">
					<tr><th scope="row">Exibir seção</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ?? '1', '1' ); ?>> Mostrar estes benefícios nas páginas individuais</label></td></tr>
					<tr><th scope="row"><label for="serben-benefits-eyebrow">Chamada</label></th><td><input class="regular-text" id="serben-benefits-eyebrow" name="eyebrow" value="<?php echo esc_attr( $settings['eyebrow'] ?? '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="serben-benefits-title">Título</label></th><td><input class="large-text" id="serben-benefits-title" name="title" value="<?php echo esc_attr( $settings['title'] ?? '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="serben-benefits-intro">Texto de apoio</label></th><td><textarea class="large-text" rows="3" id="serben-benefits-intro" name="intro"><?php echo esc_textarea( $settings['intro'] ?? '' ); ?></textarea></td></tr>
					<tr><th scope="row"><label for="serben-benefits-insurance-title">Seguro</label></th><td><input class="large-text" id="serben-benefits-insurance-title" name="insurance_title" value="<?php echo esc_attr( $settings['insurance_title'] ?? '' ); ?>"><p><input class="regular-text" name="insurance_value" value="<?php echo esc_attr( $settings['insurance_value'] ?? '' ); ?>" placeholder="R$ 15.000,00"></p></td></tr>
					<tr><th scope="row"><label for="serben-benefits-telemedicine">Telemedicina</label></th><td><input class="large-text" id="serben-benefits-telemedicine" name="telemedicine_title" value="<?php echo esc_attr( $settings['telemedicine_title'] ?? '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="serben-benefits-medicines">Medicamentos</label></th><td><input class="large-text" id="serben-benefits-medicines" name="medicines_title" value="<?php echo esc_attr( $settings['medicines_title'] ?? '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="serben-benefits-home">Assistência residencial</label></th><td><input class="large-text" id="serben-benefits-home" name="home_assistance_title" value="<?php echo esc_attr( $settings['home_assistance_title'] ?? '' ); ?>"></td></tr>
					<tr><th scope="row"><label for="serben-benefits-club">Clube de vantagens</label></th><td><input class="large-text" id="serben-benefits-club" name="club_title" value="<?php echo esc_attr( $settings['club_title'] ?? '' ); ?>"></td></tr>
				</table>
				<?php submit_button( __( 'Salvar benefícios', 'serben-clube-beneficios' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function render_maintenance(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		$this->admin_notice_from_query();
		$count = wp_count_posts( SERBEN_POST_TYPE );
		$total = 0;
		foreach ( (array) $count as $value ) {
			$total += absint( $value );
		}
		?>
		<div class="wrap serben-admin-page">
			<h1><?php esc_html_e( 'Manutenção do Clube Serben', 'serben-clube-beneficios' ); ?></h1>
			<div class="serben-card">
				<h2><?php esc_html_e( 'Capas oficiais em lote', 'serben-clube-beneficios' ); ?></h2>
				<p><?php esc_html_e( 'A busca inteligente analisa o site oficial em camadas: Open Graph/Twitter, dados estruturados de produtos, imagens reais da página, páginas internas de loja/produtos e sitemap. A melhor imagem validada é importada para a Biblioteca de Mídia e usada no listing e na página individual.', 'serben-clube-beneficios' ); ?></p>
				<?php $cover_job = class_exists( 'Serben_Cover_Studio' ) ? Serben_Cover_Studio::bulk_status() : array(); ?>
				<p style="margin:12px 0 18px"><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=serben_repair_partner_media' ), 'serben_repair_partner_media' ) ); ?>">Revisar mídias quebradas</a> <span class="description">Remove referências inválidas sem apagar mídias válidas.</span></p>
				<?php if ( ! empty( $cover_job['total'] ) ) : ?>
					<p><strong><?php echo ! empty( $cover_job['finished'] ) ? 'Última busca concluída' : 'Busca em andamento'; ?>:</strong>
					<?php echo esc_html( sprintf( '%d/%d processados · %d capas encontradas · %d sem site · %d sem imagem compatível · %d falhas de acesso/importação', absint( $cover_job['processed'] ?? 0 ), absint( $cover_job['total'] ?? 0 ), absint( $cover_job['success'] ?? 0 ), absint( $cover_job['no_site'] ?? 0 ), absint( $cover_job['not_found'] ?? 0 ), absint( $cover_job['failed'] ?? 0 ) ) ); ?></p>
				<?php endif; ?>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Executar a busca inteligente de capas nos parceiros sem capa?');">
					<?php wp_nonce_field( 'serben_bulk_official_covers' ); ?>
					<input type="hidden" name="action" value="serben_bulk_official_covers">
					<input type="hidden" name="scope" value="missing">
					<p><label><input type="checkbox" name="replace_existing" value="1"> <?php esc_html_e( 'Refazer também parceiros que já possuem capa editorial', 'serben-clube-beneficios' ); ?></label></p>
					<?php submit_button( __( 'Buscar capas com o motor inteligente', 'serben-clube-beneficios' ), 'primary', 'submit', false ); ?>
				</form>

				<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'serben_bulk_official_covers' ); ?>
						<input type="hidden" name="action" value="serben_bulk_official_covers"><input type="hidden" name="scope" value="not_found">
						<button class="button" type="submit">Tentar novamente sem imagem</button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'serben_bulk_official_covers' ); ?>
						<input type="hidden" name="action" value="serben_bulk_official_covers"><input type="hidden" name="scope" value="failed">
						<button class="button" type="submit">Tentar novamente as falhas</button>
					</form>
				</div>
				<p class="description"><?php esc_html_e( 'O motor rejeita logos, ícones, placeholders e arquivos muito pequenos como capa. Sites que bloqueiam robôs/CDNs podem continuar exigindo seleção manual.', 'serben-clube-beneficios' ); ?></p>
			</div>

			<div class="serben-card">
				<h2><?php esc_html_e( 'Capas visuais dos parceiros', 'serben-clube-beneficios' ); ?></h2>
				<p><?php esc_html_e( 'Aplique a identidade Club Serben aos parceiros atuais usando a categoria principal e o logo cadastrado.', 'serben-clube-beneficios' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Regenerar as capas de todos os parceiros?');">
					<?php wp_nonce_field( 'serben_regenerate_all_covers' ); ?>
					<input type="hidden" name="action" value="serben_regenerate_all_covers">
					<?php submit_button( __( 'Regenerar capas de todos os parceiros', 'serben-clube-beneficios' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<div class="serben-card">
				<h2><?php esc_html_e( 'Gestão e limpeza de logos', 'serben-clube-beneficios' ); ?></h2>
				<p><?php esc_html_e( 'Os novos logos importados por ZIP passam a ser marcados como arquivos gerenciados. Ao substituir um logo, o anterior pode ser removido com segurança se não estiver sendo usado. Esta limpeza apaga somente logos gerenciados pelo plugin que ficaram órfãos.', 'serben-clube-beneficios' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=serben_cleanup_partner_logos' ), 'serben_cleanup_partner_logos' ) ); ?>" onclick="return confirm('Apagar permanentemente logos antigos gerenciados pelo plugin que não estão em uso?');">Limpar logos antigos não utilizados</a></p>
			</div>

			<div class="serben-card">
				<h2><?php esc_html_e( 'Limpar importação incorreta', 'serben-clube-beneficios' ); ?></h2>
				<p><?php printf( esc_html__( 'Parceiros cadastrados atualmente: %d.', 'serben-clube-beneficios' ), absint( $total ) ); ?></p>
				<p><?php esc_html_e( 'Use este recurso para remover todos os parceiros do CPT Serben e suas filiais antes de reimportar a planilha corrigida. A mídia enviada para a biblioteca não é apagada.', 'serben-clube-beneficios' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Tem certeza? Todos os parceiros Serben serão removidos permanentemente.');">
					<?php wp_nonce_field( 'serben_purge_partners', 'serben_purge_nonce' ); ?>
					<input type="hidden" name="action" value="serben_purge_partners">
					<?php submit_button( __( 'Apagar todos os parceiros Serben', 'serben-clube-beneficios' ), 'delete' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public function render_export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		?>
		<div class="wrap serben-admin-page">
			<h1><?php esc_html_e( 'Exportar Parceiros', 'serben-clube-beneficios' ); ?></h1>
			<div class="serben-card">
				<p><?php esc_html_e( 'Gere uma exportação com parceiros, categorias, metadados e filiais.', 'serben-clube-beneficios' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=serben_export_excel' ), 'serben_export_excel' ) ); ?>"><?php esc_html_e( 'Baixar exportação', 'serben-clube-beneficios' ); ?></a></p>
			</div>
		</div>
		<?php
	}

	public function handle_import_excel(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		check_admin_referer( 'serben_import_excel', 'serben_import_excel_nonce' );

		if ( empty( $_FILES['serben_excel_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Arquivo não enviado.', 'serben-clube-beneficios' ) );
		}

		$importer = new Serben_Import_Excel();
		$result   = $importer->import( $_FILES['serben_excel_file'], array(
			'update_existing' => ! empty( $_POST['update_existing'] ),
			'publish'         => ! empty( $_POST['publish'] ),
		) );

		$notice = sprintf(
			/* translators: 1: created, 2: updated, 3: branches, 4: categories */
			__( 'Importação concluída. Criados: %1$d. Atualizados: %2$d. Filiais: %3$d. Categorias: %4$d.', 'serben-clube-beneficios' ),
			absint( $result['created'] ),
			absint( $result['updated'] ),
			absint( $result['branches'] ),
			absint( $result['categories'] )
		);

		wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( $notice ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-import-excel' ) ) );
		exit;
	}

	public function handle_import_logos(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		check_admin_referer( 'serben_import_logos', 'serben_import_logos_nonce' );

		if ( empty( $_FILES['serben_logos_zip']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Arquivo ZIP não enviado.', 'serben-clube-beneficios' ) );
		}

		$importer = new Serben_Import_Logos();
		$result   = $importer->import( $_FILES['serben_logos_zip'], array( 'replace_existing' => ! empty( $_POST['replace_existing'] ) ) );
		$notice   = sprintf(
			__( 'Importação de logos concluída. Vinculados: %1$d. Ignorados: %2$d.', 'serben-clube-beneficios' ),
			absint( $result['linked'] ),
			absint( $result['skipped'] )
		);

		wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( $notice ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-import-logos' ) ) );
		exit;
	}


	public function handle_purge_partners(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		check_admin_referer( 'serben_purge_partners', 'serben_purge_nonce' );

		$q = new WP_Query(
			array(
				'post_type'      => SERBEN_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$deleted = 0;
		foreach ( $q->posts as $post_id ) {
			if ( wp_delete_post( absint( $post_id ), true ) ) {
				$deleted++;
			}
		}

		Serben_Utils::clear_query_cache();
		$notice = sprintf( __( 'Limpeza concluída. Parceiros removidos: %d. Agora importe novamente a planilha.', 'serben-clube-beneficios' ), absint( $deleted ) );
		wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( $notice ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-maintenance' ) ) );
		exit;
	}


	public function handle_save_club_benefits(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permissão insuficiente.', 'serben-clube-beneficios' ) );
		}
		check_admin_referer( 'serben_save_club_benefits', 'serben_club_benefits_nonce' );
		$fields = array( 'eyebrow', 'title', 'intro', 'insurance_title', 'insurance_value', 'telemedicine_title', 'medicines_title', 'home_assistance_title', 'club_title' );
		$data = array( 'enabled' => ! empty( $_POST['enabled'] ) ? '1' : '0' );
		foreach ( $fields as $field ) {
			$data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}
		update_option( 'serben_club_benefits', $data, false );
		wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( 'Benefícios atualizados com sucesso.' ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-club-benefits' ) ) );
		exit;
	}

	public function columns( array $columns ): array {
		$columns['serben_logo']     = __( 'Logo', 'serben-clube-beneficios' );
		$columns['serben_tipo']     = __( 'Tipo', 'serben-clube-beneficios' );
		$columns['serben_desconto'] = __( 'Desconto', 'serben-clube-beneficios' );
		$columns['serben_cidade']   = __( 'Cidades', 'serben-clube-beneficios' );
		$columns['serben_metricas'] = __( 'Métricas', 'serben-clube-beneficios' );
		return $columns;
	}

	public function column_content( string $column, int $post_id ): void {
		if ( 'serben_logo' === $column ) {
			$logo = class_exists( 'Serben_Utils' ) ? Serben_Utils::partner_logo_url( $post_id, 'thumbnail' ) : '';
			echo $logo ? '<img src="' . esc_url( $logo ) . '" alt="" style="width:48px;height:48px;object-fit:contain;background:#fff;border:1px solid #eee;border-radius:8px">' : '—';
		}
		if ( 'serben_tipo' === $column ) {
			echo esc_html( ucfirst( (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', true ) ) );
		}
		if ( 'serben_desconto' === $column ) {
			$tipo = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', true );
			echo esc_html( (string) get_post_meta( $post_id, SERBEN_META_PREFIX . ( 'online' === $tipo ? 'desconto_online' : 'desconto' ), true ) );
		}
		if ( 'serben_cidade' === $column ) {
			$cities = array_filter( array_unique( wp_list_pluck( Serben_Branches::get_for_post( $post_id ), 'city' ) ) );
			echo esc_html( implode( ', ', $cities ) ?: '—' );
		}
		if ( 'serben_metricas' === $column ) {
			printf(
				'👁 %s<br>💬 %s',
				esc_html( number_format_i18n( absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'views', true ) ) ) ),
				esc_html( number_format_i18n( absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'whatsapp_clicks', true ) ) ) )
			);
		}
	}

	public function sortable_columns( array $columns ): array {
		$columns['serben_tipo'] = 'serben_tipo';
		return $columns;
	}
}
