<?php
/**
 * Partner editing fields.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Meta_Boxes {
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . SERBEN_POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || SERBEN_POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'serben-admin', SERBEN_PLUGIN_URL . 'assets/css/admin.css', array(), SERBEN_PLUGIN_VERSION );
		wp_enqueue_script( 'serben-admin', SERBEN_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SERBEN_PLUGIN_VERSION, true );
		if ( class_exists( 'Serben_Awin' ) ) wp_localize_script( 'serben-admin', 'SerbenAdmin', Serben_Awin::admin_script_data() );
	}

	public function add_meta_boxes(): void {
		add_meta_box(
			'serben_partner_details',
			__( 'Dados do parceiro', 'serben-clube-beneficios' ),
			array( $this, 'render_partner_details' ),
			SERBEN_POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'serben_partner_branches',
			__( 'Filiais / Unidades', 'serben-clube-beneficios' ),
			array( $this, 'render_branches' ),
			SERBEN_POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'serben_partner_stats',
			__( 'Métricas', 'serben-clube-beneficios' ),
			array( $this, 'render_stats' ),
			SERBEN_POST_TYPE,
			'side',
			'default'
		);
	}

	private function field_value( int $post_id, string $key ): string {
		return (string) get_post_meta( $post_id, SERBEN_META_PREFIX . $key, true );
	}

	private function image_field( int $post_id, string $key, string $label ): void {
		$id  = absint( get_post_meta( $post_id, SERBEN_META_PREFIX . $key, true ) );
		$is_logo = 'logo_id' === $key;
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : ( $is_logo && class_exists( 'Serben_Utils' ) ? Serben_Utils::partner_logo_url( $post_id, 'medium' ) : '' );
		$mode = $is_logo ? sanitize_key( (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_mode', true ) ) : '';
		if ( $is_logo && ! in_array( $mode, array( 'local', 'none', 'legacy' ), true ) ) $mode = $id ? 'local' : 'legacy';
		?>
		<div class="serben-field serben-image-field<?php echo $is_logo ? ' serben-logo-manager' : ''; ?>" data-key="<?php echo esc_attr( $key ); ?>">
			<label><?php echo esc_html( $label ); ?></label>
			<input type="hidden" name="serben_meta[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $id ); ?>" class="serben-image-id">
			<?php if ( $is_logo ) : ?>
				<input type="hidden" name="serben_meta[logo_mode]" value="<?php echo esc_attr( $mode ); ?>" class="serben-logo-mode">
				<input type="hidden" name="serben_meta[logo_previous_id]" value="<?php echo esc_attr( $id ); ?>">
			<?php endif; ?>
			<div class="serben-image-preview"><?php echo $src ? '<img src="' . esc_url( $src ) . '" alt="">' : ''; ?></div>
			<div class="serben-image-actions">
				<button type="button" class="button serben-upload-image"><?php echo esc_html( $is_logo ? __( 'Trocar logo', 'serben-clube-beneficios' ) : __( 'Selecionar imagem', 'serben-clube-beneficios' ) ); ?></button>
				<button type="button" class="button serben-remove-image"><?php echo esc_html( $is_logo ? __( 'Remover logo', 'serben-clube-beneficios' ) : __( 'Remover', 'serben-clube-beneficios' ) ); ?></button>
			</div>
			<?php if ( $is_logo ) : ?>
				<p class="serben-help-text"><strong><?php esc_html_e( 'Logo oficial do card.', 'serben-clube-beneficios' ); ?></strong> <?php esc_html_e( 'Ao trocar, o novo arquivo passa a ter prioridade imediata. “Remover logo” também bloqueia logos antigos vindos de importações/URLs legadas, evitando que reapareçam.', 'serben-clube-beneficios' ); ?></p>
				<div class="serben-awin-logo-tools" data-post-id="<?php echo esc_attr( $post_id ); ?>">
					<div class="serben-awin-logo-head">
						<strong>Awin</strong>
						<?php $awin_id = absint( get_post_meta( $post_id, 'awin_advertiser_id', true ) ); if ( $awin_id ) : ?><span>Advertiser ID <?php echo esc_html( $awin_id ); ?></span><?php endif; ?>
					</div>
					<?php if ( class_exists( 'Serben_Awin' ) && Serben_Awin::is_configured() ) : ?>
						<button type="button" class="button serben-awin-find-logo">Pesquisar logo na Awin</button>
						<span class="serben-awin-status" aria-live="polite"></span>
						<div class="serben-awin-results"></div>
					<?php else : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-awin' ) ); ?>">Configurar Awin</a>
						<span class="description">Depois de configurar, você poderá pesquisar pelo nome e importar o logo oficial com um clique.</span>
					<?php endif; ?>
				</div>
				<label class="serben-logo-cleanup"><input type="checkbox" name="serben_meta[logo_cleanup_old]" value="1" checked> <?php esc_html_e( 'Apagar o arquivo anterior somente quando ele tiver sido importado/gerenciado pelo plugin e não estiver em uso.', 'serben-clube-beneficios' ); ?></label>
			<?php endif; ?>
		</div>
		<?php
	}


	private function gallery_field( int $post_id ): void {
		$raw = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'gallery_ids', true );
		$ids = array_values( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', $raw ) ) ) );
		?>
		<div class="serben-field serben-gallery-field">
			<label><?php esc_html_e( 'Galeria de fotos do parceiro', 'serben-clube-beneficios' ); ?></label>
			<input type="hidden" name="serben_meta[gallery_ids]" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>" class="serben-gallery-ids">
			<div class="serben-gallery-preview">
				<?php foreach ( $ids as $image_id ) : $src = wp_get_attachment_image_url( $image_id, 'thumbnail' ); if ( ! $src ) continue; ?>
					<span data-id="<?php echo esc_attr( $image_id ); ?>"><img src="<?php echo esc_url( $src ); ?>" alt=""></span>
				<?php endforeach; ?>
			</div>
			<div class="serben-gallery-actions">
				<button type="button" class="button serben-select-gallery"><?php esc_html_e( 'Selecionar fotos', 'serben-clube-beneficios' ); ?></button>
				<button type="button" class="button serben-clear-gallery"><?php esc_html_e( 'Limpar galeria', 'serben-clube-beneficios' ); ?></button>
			</div>
			<p class="serben-help-text"><?php esc_html_e( 'Use fotos do ambiente, serviços, equipe, produtos ou experiências. Recomendado: 4 a 8 imagens horizontais de boa qualidade.', 'serben-clube-beneficios' ); ?></p>
		</div>
		<?php
	}

	public function render_partner_details( WP_Post $post ): void {
		wp_nonce_field( 'serben_save_partner', 'serben_partner_nonce' );
		$type = $this->field_value( $post->ID, 'tipo' ) ?: 'fisico';
		?>
		<div class="serben-admin-panel">
			<div class="serben-grid serben-grid-2">
				<div class="serben-field">
					<label for="serben-tipo"><?php esc_html_e( 'Tipo do parceiro', 'serben-clube-beneficios' ); ?></label>
					<select name="serben_meta[tipo]" id="serben-tipo" class="serben-partner-type">
						<option value="fisico" <?php selected( $type, 'fisico' ); ?>><?php esc_html_e( 'Físico', 'serben-clube-beneficios' ); ?></option>
						<option value="online" <?php selected( $type, 'online' ); ?>><?php esc_html_e( 'Online', 'serben-clube-beneficios' ); ?></option>
					</select>
				</div>
				<div class="serben-field serben-checkbox-field">
					<label><input type="checkbox" name="serben_meta[destaque]" value="1" <?php checked( $this->field_value( $post->ID, 'destaque' ), '1' ); ?>> <?php esc_html_e( 'Parceiro em destaque', 'serben-clube-beneficios' ); ?></label>
				</div>
			</div>

			<div class="serben-grid serben-grid-2">
				<?php $this->image_field( $post->ID, 'logo_id', __( 'Logo', 'serben-clube-beneficios' ) ); ?>
				<?php $this->image_field( $post->ID, 'banner_id', __( 'Imagem de capa / galeria', 'serben-clube-beneficios' ) ); ?>
			</div>

			<p class="serben-media-rule"><strong>Mídia no site:</strong> parceiros online usam o logo como imagem principal dos cards e da página individual; a imagem de capa entra como conteúdo secundário da galeria. Parceiros presenciais podem priorizar a capa quando ela existir.</p>


			<div class="serben-field">
				<label for="serben-brand-color"><?php esc_html_e( 'Cor principal da marca', 'serben-clube-beneficios' ); ?></label>
				<div style="display:flex;gap:10px;align-items:center;max-width:420px">
					<input id="serben-brand-color" type="text" name="serben_meta[brand_color]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'brand_color' ) ?: '#FFFFFF' ); ?>" placeholder="#FFFFFF" pattern="^#?[0-9A-Fa-f]{6}$" style="flex:1">
					<input type="color" value="<?php echo esc_attr( $this->field_value( $post->ID, 'brand_color' ) ?: '#FFFFFF' ); ?>" aria-label="Selecionar cor da marca" oninput="document.getElementById('serben-brand-color').value=this.value.toUpperCase()">
				</div>
				<p class="serben-help-text"><?php esc_html_e( 'Cor de fundo usada quando o parceiro não possui capa. O padrão é branco (#FFFFFF). Escolha a cor oficial da marca quando quiser. O logo é exibido sem filtros e sem alteração de cor.', 'serben-clube-beneficios' ); ?></p>
			</div>

			<div class="serben-grid serben-grid-3 serben-redemption-admin-fields">
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Título da oferta', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[titulo_oferta]" placeholder="Ex.: Ganhe 20% de desconto" value="<?php echo esc_attr( $this->field_value( $post->ID, 'titulo_oferta' ) ); ?>"><p class="serben-help-text"><?php esc_html_e( 'Este título aparece em destaque na página da oferta.', 'serben-clube-beneficios' ); ?></p></div>
				<div class="serben-field"><label><?php esc_html_e( 'Custo em Bumes', 'serben-clube-beneficios' ); ?></label><input type="number" min="1" step="1" name="serben_meta[custo_bumes]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'custo_bumes' ) ); ?>" placeholder="30"><p class="serben-help-text"><?php esc_html_e( 'Sem este valor o resgate fica bloqueado.', 'serben-clube-beneficios' ); ?></p></div>
				<div class="serben-field"><label><?php esc_html_e( 'Limite por associado', 'serben-clube-beneficios' ); ?></label><input type="number" min="1" step="1" name="serben_meta[limite_resgates]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'limite_resgates' ) ?: '1' ); ?>"><p class="serben-help-text"><?php esc_html_e( 'Quantidade máxima de resgates deste benefício por usuário.', 'serben-clube-beneficios' ); ?></p></div>
			</div>

			<div class="serben-field">
				<label for="serben-descricao"><?php esc_html_e( 'Descrição', 'serben-clube-beneficios' ); ?></label>
				<textarea id="serben-descricao" name="serben_meta[descricao]" rows="4"><?php echo esc_textarea( $this->field_value( $post->ID, 'descricao' ) ); ?></textarea>
			</div>

			<div class="serben-field">
				<label for="serben-descricao-beneficio"><?php esc_html_e( 'Descrição do benefício / desconto', 'serben-clube-beneficios' ); ?></label>
				<textarea id="serben-descricao-beneficio" name="serben_meta[descricao_beneficio]" rows="3" placeholder="Ex.: Associados Club Serben têm até 30% de desconto nos planos participantes."><?php echo esc_textarea( $this->field_value( $post->ID, 'descricao_beneficio' ) ); ?></textarea>
				<p class="serben-help-text"><?php esc_html_e( 'Explique a vantagem de forma clara. Evite textos genéricos como “conforme descrição”.', 'serben-clube-beneficios' ); ?></p>
			</div>

			<div class="serben-field serben-checkbox-field">
				<label><input type="checkbox" name="serben_meta[nao_cumulativo]" value="1" <?php checked( '0' !== $this->field_value( $post->ID, 'nao_cumulativo' ) ); ?>> <?php esc_html_e( 'Benefício não cumulativo com outras promoções', 'serben-clube-beneficios' ); ?></label>
				<p class="serben-help-text"><?php esc_html_e( 'Regra padrão do Club Serben. Desmarque somente quando houver exceção aprovada.', 'serben-clube-beneficios' ); ?></p>
			</div>

			<div class="serben-grid serben-grid-2 serben-physical-fields">
				<div class="serben-field"><label><?php esc_html_e( 'Desconto oferecido', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[desconto]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'desconto' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'WhatsApp', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[whatsapp]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'whatsapp' ) ); ?>"></div>
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Regras do benefício', 'serben-clube-beneficios' ); ?></label><textarea name="serben_meta[regras]" rows="3"><?php echo esc_textarea( $this->field_value( $post->ID, 'regras' ) ); ?></textarea></div>
				<div class="serben-field"><label><?php esc_html_e( 'Site', 'serben-clube-beneficios' ); ?></label><input type="url" name="serben_meta[site]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'site' ) ); ?>"></div>
			</div>

			<div class="serben-grid serben-grid-2 serben-online-fields">
				<div class="serben-field"><label><?php esc_html_e( 'Link do parceiro', 'serben-clube-beneficios' ); ?></label><input type="url" name="serben_meta[link_parceiro]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'link_parceiro' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Cupom', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[cupom]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'cupom' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Desconto / Cash', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[desconto_online]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'desconto_online' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Validade opcional', 'serben-clube-beneficios' ); ?></label><input type="date" name="serben_meta[validade]" value="<?php echo esc_attr( $this->field_value( $post->ID, 'validade' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Texto do botão de resgate', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[resgate_botao]" placeholder="Resgatar cupom" value="<?php echo esc_attr( $this->field_value( $post->ID, 'resgate_botao' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Título do bloco de resgate', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_meta[resgate_titulo]" placeholder="Seu benefício está pronto" value="<?php echo esc_attr( $this->field_value( $post->ID, 'resgate_titulo' ) ); ?>"></div>
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Como utilizar', 'serben-clube-beneficios' ); ?></label><textarea name="serben_meta[como_utilizar]" rows="3"><?php echo esc_textarea( $this->field_value( $post->ID, 'como_utilizar' ) ); ?></textarea></div>
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Observação do resgate', 'serben-clube-beneficios' ); ?></label><textarea name="serben_meta[resgate_observacao]" rows="2" placeholder="Ex.: Copie o cupom e finalize a compra no site parceiro."><?php echo esc_textarea( $this->field_value( $post->ID, 'resgate_observacao' ) ); ?></textarea></div>
				<div class="serben-field serben-checkbox-field"><label><input type="checkbox" name="serben_meta[resgate_som]" value="1" <?php checked( '0' !== $this->field_value( $post->ID, 'resgate_som' ) ); ?>> <?php esc_html_e( 'Tocar som ao clicar em resgatar', 'serben-clube-beneficios' ); ?></label><p class="serben-help-text"><?php esc_html_e( 'O som é reproduzido no clique do usuário e respeita as permissões do navegador.', 'serben-clube-beneficios' ); ?></p></div>
			</div>


			<hr style="margin:26px 0;border:0;border-top:1px solid #eadcf0">
			<h3><?php esc_html_e( 'Vitrine do parceiro na página individual', 'serben-clube-beneficios' ); ?></h3>
			<p class="serben-help-text" style="margin-bottom:16px"><?php esc_html_e( 'Esses campos enriquecem a página do parceiro. São especialmente úteis para estabelecimentos presenciais que querem divulgar ambiente, serviços, equipe e diferenciais.', 'serben-clube-beneficios' ); ?></p>
			<div class="serben-field">
				<label><?php esc_html_e( 'Apresentação da marca', 'serben-clube-beneficios' ); ?></label>
				<textarea name="serben_meta[brand_intro]" rows="4" placeholder="Conte a história, os diferenciais e o que o associado encontra neste parceiro."><?php echo esc_textarea( $this->field_value( $post->ID, 'brand_intro' ) ); ?></textarea>
			</div>
			<div class="serben-field">
				<label><?php esc_html_e( 'Serviços / especialidades', 'serben-clube-beneficios' ); ?></label>
				<textarea name="serben_meta[services]" rows="6" placeholder="Musculação | Estrutura completa para treino de força\nAulas coletivas | Modalidades e horários variados\nPersonal trainer | Atendimento individualizado"><?php echo esc_textarea( $this->field_value( $post->ID, 'services' ) ); ?></textarea>
				<p class="serben-help-text"><?php esc_html_e( 'Use uma linha por serviço. Formato recomendado: Nome do serviço | pequena descrição.', 'serben-clube-beneficios' ); ?></p>
			</div>
			<?php $this->gallery_field( $post->ID ); ?>

			<h3><?php esc_html_e( 'Redes sociais', 'serben-clube-beneficios' ); ?></h3>
			<div class="serben-grid serben-grid-3">
				<?php foreach ( array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'tiktok' => 'TikTok' ) as $key => $label ) : ?>
					<div class="serben-field"><label><?php echo esc_html( $label ); ?></label><input type="url" name="serben_meta[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $this->field_value( $post->ID, $key ) ); ?>"></div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	public function render_branches( WP_Post $post ): void {
		$branches = Serben_Branches::get_for_post( $post->ID );
		if ( empty( $branches ) ) {
			$branches = array( array() );
		}
		?>
		<div class="serben-branches" data-next-index="<?php echo esc_attr( count( $branches ) ); ?>">
			<div class="serben-branch-list">
				<?php foreach ( $branches as $index => $branch ) : ?>
					<?php $this->render_branch_row( absint( $index ), is_array( $branch ) ? $branch : array() ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button button-primary serben-add-branch"><?php esc_html_e( 'Adicionar filial', 'serben-clube-beneficios' ); ?></button>
			<script type="text/html" id="serben-branch-template">
				<?php $this->render_branch_row( 999999, array(), true ); ?>
			</script>
		</div>
		<?php
	}

	private function render_branch_row( int $index, array $branch, bool $template = false ): void {
		$idx = $template ? '__INDEX__' : (string) $index;
		$get = static function ( string $key ) use ( $branch ): string {
			return isset( $branch[ $key ] ) ? (string) $branch[ $key ] : '';
		};
		?>
		<div class="serben-branch-item">
			<div class="serben-branch-header">
				<strong><?php esc_html_e( 'Filial / Unidade', 'serben-clube-beneficios' ); ?></strong>
				<button type="button" class="button-link-delete serben-remove-branch"><?php esc_html_e( 'Remover', 'serben-clube-beneficios' ); ?></button>
			</div>
			<div class="serben-grid serben-grid-3">
				<div class="serben-field"><label><?php esc_html_e( 'Nome da unidade', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][unit_name]" value="<?php echo esc_attr( $get( 'unit_name' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Cidade', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][city]" value="<?php echo esc_attr( $get( 'city' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Estado', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][state]" value="<?php echo esc_attr( $get( 'state' ) ); ?>"></div>
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Endereço', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][address]" value="<?php echo esc_attr( $get( 'address' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'CEP', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][zipcode]" value="<?php echo esc_attr( $get( 'zipcode' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Telefone', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][phone]" value="<?php echo esc_attr( $get( 'phone' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'WhatsApp', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][whatsapp]" value="<?php echo esc_attr( $get( 'whatsapp' ) ); ?>"></div>
				<div class="serben-field"><label><?php esc_html_e( 'Responsável', 'serben-clube-beneficios' ); ?></label><input type="text" name="serben_branches[<?php echo esc_attr( $idx ); ?>][responsible]" value="<?php echo esc_attr( $get( 'responsible' ) ); ?>"></div>
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Horário de funcionamento', 'serben-clube-beneficios' ); ?></label><textarea name="serben_branches[<?php echo esc_attr( $idx ); ?>][hours]" rows="2"><?php echo esc_textarea( $get( 'hours' ) ); ?></textarea></div>
				<div class="serben-field serben-col-span"><label><?php esc_html_e( 'Link do Google Maps', 'serben-clube-beneficios' ); ?></label><input type="url" name="serben_branches[<?php echo esc_attr( $idx ); ?>][maps_url]" value="<?php echo esc_attr( $get( 'maps_url' ) ); ?>"></div>
			</div>
		</div>
		<?php
	}

	public function render_stats( WP_Post $post ): void {
		$views  = absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'views', true ) );
		$clicks = absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'whatsapp_clicks', true ) );
		$redemptions = absint( get_post_meta( $post->ID, SERBEN_META_PREFIX . 'resgate_clicks', true ) );
		?>
		<p><strong><?php esc_html_e( 'Visualizações:', 'serben-clube-beneficios' ); ?></strong> <?php echo esc_html( number_format_i18n( $views ) ); ?></p>
		<p><strong><?php esc_html_e( 'Cliques no WhatsApp:', 'serben-clube-beneficios' ); ?></strong> <?php echo esc_html( number_format_i18n( $clicks ) ); ?></p>
		<p><strong><?php esc_html_e( 'Resgates de cupom:', 'serben-clube-beneficios' ); ?></strong> <?php echo esc_html( number_format_i18n( $redemptions ) ); ?></p>
		<?php
	}

	public function save( int $post_id ): void {
		if ( ! isset( $_POST['serben_partner_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['serben_partner_nonce'] ) ), 'serben_save_partner' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$meta = isset( $_POST['serben_meta'] ) && is_array( $_POST['serben_meta'] ) ? wp_unslash( $_POST['serben_meta'] ) : array();

		$text_fields = array( 'tipo', 'titulo_oferta', 'desconto', 'desconto_online', 'cupom', 'validade', 'resgate_botao', 'resgate_titulo' );
		foreach ( $text_fields as $key ) {
			$value = isset( $meta[ $key ] ) ? sanitize_text_field( $meta[ $key ] ) : '';
			if ( 'tipo' === $key && ! in_array( $value, array( 'fisico', 'online' ), true ) ) {
				$value = 'fisico';
			}
			update_post_meta( $post_id, SERBEN_META_PREFIX . $key, $value );
		}


		$old_logo_id = absint( get_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', true ) );
		$new_logo_id = isset( $meta['logo_id'] ) ? absint( $meta['logo_id'] ) : 0;
		$logo_mode = isset( $meta['logo_mode'] ) ? sanitize_key( $meta['logo_mode'] ) : '';
		if ( $new_logo_id > 0 ) $logo_mode = 'local';
		elseif ( 'none' !== $logo_mode ) $logo_mode = 'legacy';

		$brand_color = isset( $meta['brand_color'] ) ? strtoupper( sanitize_text_field( $meta['brand_color'] ) ) : '#FFFFFF';
		if ( ! preg_match( '/^#[0-9A-F]{6}$/', $brand_color ) ) {
			$brand_color = '#FFFFFF';
		}
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'brand_color', $brand_color );
		if ( $new_logo_id !== $old_logo_id ) {
			delete_post_meta( $post_id, '_serben_brand_color_auto' );
			delete_post_meta( $post_id, '_serben_brand_color_auto_logo_id' );
		}

		$textarea_fields = array( 'descricao', 'descricao_beneficio', 'regras', 'como_utilizar', 'resgate_observacao', 'brand_intro', 'services' );
		foreach ( $textarea_fields as $key ) {
			update_post_meta( $post_id, SERBEN_META_PREFIX . $key, isset( $meta[ $key ] ) ? sanitize_textarea_field( $meta[ $key ] ) : '' );
		}

		$url_fields = array( 'site', 'instagram', 'facebook', 'youtube', 'tiktok', 'link_parceiro' );
		foreach ( $url_fields as $key ) {
			update_post_meta( $post_id, SERBEN_META_PREFIX . $key, isset( $meta[ $key ] ) ? Serben_Utils::sanitize_url_or_empty( $meta[ $key ] ) : '' );
		}

		update_post_meta( $post_id, SERBEN_META_PREFIX . 'whatsapp', isset( $meta['whatsapp'] ) ? Serben_Utils::sanitize_phone( $meta['whatsapp'] ) : '' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'destaque', ! empty( $meta['destaque'] ) ? '1' : '0' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'nao_cumulativo', ! empty( $meta['nao_cumulativo'] ) ? '1' : '0' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'resgate_som', ! empty( $meta['resgate_som'] ) ? '1' : '0' );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'custo_bumes', isset( $meta['custo_bumes'] ) ? max( 0, absint( $meta['custo_bumes'] ) ) : 0 );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'limite_resgates', isset( $meta['limite_resgates'] ) ? max( 1, absint( $meta['limite_resgates'] ) ) : 1 );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_id', $new_logo_id );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'logo_mode', $logo_mode );
		if ( $new_logo_id > 0 && Serben_Utils::is_managed_logo( $new_logo_id, $post_id ) ) Serben_Utils::mark_managed_logo( $new_logo_id, $post_id, 'existing-managed' );
		if ( $old_logo_id && $old_logo_id !== $new_logo_id && ! empty( $meta['logo_cleanup_old'] ) ) Serben_Utils::maybe_delete_managed_logo( $old_logo_id, $post_id );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'banner_id', isset( $meta['banner_id'] ) ? absint( $meta['banner_id'] ) : 0 );

		$gallery_ids = isset( $meta['gallery_ids'] ) ? preg_split( '/\s*,\s*/', (string) $meta['gallery_ids'] ) : array();
		$gallery_ids = array_values( array_unique( array_filter( array_map( 'absint', $gallery_ids ) ) ) );
		update_post_meta( $post_id, SERBEN_META_PREFIX . 'gallery_ids', implode( ',', array_slice( $gallery_ids, 0, 12 ) ) );

		$branches = isset( $_POST['serben_branches'] ) && is_array( $_POST['serben_branches'] ) ? $_POST['serben_branches'] : array();
		Serben_Branches::save_for_post( $post_id, $branches );
		Serben_Utils::clear_query_cache();
	}
}
