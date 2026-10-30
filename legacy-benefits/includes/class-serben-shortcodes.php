<?php
/**
 * Front-end shortcodes and cards.
 */

defined( 'ABSPATH' ) || exit;

class Serben_Shortcodes {
	public function init(): void {
		add_shortcode( 'serben_parceiros', array( $this, 'partners' ) );
		add_shortcode( 'serben_online', array( $this, 'online' ) );
		add_shortcode( 'serben_busca', array( $this, 'search' ) );
		add_shortcode( 'serben_categorias', array( $this, 'categories' ) );
		add_shortcode( 'serben_banner_parceiros', array( $this, 'partners_banner' ) );
		add_shortcode( 'serben_credenciados', array( $this, 'credenciados' ) );
	}


	public function credenciados( array $atts = array() ): string {
		$atts = shortcode_atts( array( 'per_page' => 24 ), $atts, 'serben_credenciados' );
		wp_enqueue_style( 'serben-public' ); wp_enqueue_script( 'serben-public' );
		return $this->search( array( 'per_page' => max( 1, min( 48, absint( $atts['per_page'] ) ) ) ) );
	}

	public function partners_banner( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'link'   => 'https://bumemania.com.br/clubserben/credenciados/',
				'alt'    => __( 'Banner de parceiros credenciados Serben', 'serben-clube-beneficios' ),
				'target' => '',
			),
			$atts,
			'serben_banner_parceiros'
		);
		wp_enqueue_style( 'serben-public' );
		$src    = SERBEN_PLUGIN_URL . 'assets/images/banner-parceiros-serben.png';
		$link   = trim( (string) $atts['link'] );
		$target = '_blank' === $atts['target'] ? ' target="_blank" rel="noopener"' : '';
		$image  = sprintf(
			'<img class="serben-partners-banner-img" src="%s" alt="%s" loading="eager" decoding="async">',
			esc_url( $src ),
			esc_attr( (string) $atts['alt'] )
		);
		return '<div class="serben-partners-banner">' . ( $link ? sprintf( '<a href="%s"%s>%s</a>', esc_url( $link ), $target, $image ) : $image ) . '</div>';
	}

	public function partners( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'categoria' => '',
				'tipo'      => '',
				'cidade'    => '',
				'destaque'  => '',
				'per_page'  => 12,
			),
			$atts,
			'serben_parceiros'
		);
		return $this->render_listing( $atts );
	}

	public function online( array $atts = array() ): string {
		$atts         = shortcode_atts( array( 'categoria' => '', 'per_page' => 12 ), $atts, 'serben_online' );
		$atts['tipo'] = 'online';
		return $this->render_listing( $atts );
	}

	public function search( array $atts = array() ): string {
		$atts = shortcode_atts( array( 'per_page' => 12 ), $atts, 'serben_busca' );
		return $this->render_listing( $atts, true );
	}

	public function categories(): string {
		$catalogue = class_exists( 'Serben_Utils' ) ? Serben_Utils::market_categories() : array();
		wp_enqueue_style( 'serben-public' );
		$offers_url = home_url( '/credenciados/' );
		ob_start();
		?>
		<div class="serben-category-shell" aria-label="Principais categorias do Club Serben">
			<a class="serben-category-pill is-all" href="<?php echo esc_url( $offers_url ); ?>">
				<span class="serben-category-icon"><?php echo Serben_Utils::fa_icon( 'grid' ); ?></span>
				<span>Todos</span>
			</a>
			<div class="serben-category-scroll">
				<div class="serben-category-strip">
					<?php foreach ( $catalogue as $group ) :
						$slug = (string) ( $group['slug'] ?? sanitize_title( (string) $group['name'] ) );
						$url  = add_query_arg( 'categoria', $slug, $offers_url );
					?>
						<a class="serben-category-pill" href="<?php echo esc_url( $url ); ?>">
							<span class="serben-category-icon"><?php echo Serben_Utils::fa_icon( (string) ( $group['icon'] ?? 'tag' ) ); ?></span>
							<span><?php echo esc_html( $group['name'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_listing( array $atts, bool $force_filters = false ): string {
		wp_enqueue_style( 'serben-public' );
		wp_enqueue_script( 'serben-public' );

		$atts['per_page'] = max( 1, min( 48, absint( $atts['per_page'] ?? 12 ) ) );
		$show_filters     = $force_filters || empty( $atts['categoria'] ) && empty( $atts['tipo'] ) && empty( $atts['cidade'] );
		$query_args       = array(
			's'         => '',
			'categoria' => sanitize_title( (string) ( $atts['categoria'] ?? '' ) ),
			'tipo'      => sanitize_key( (string) ( $atts['tipo'] ?? '' ) ),
			'cidade'    => sanitize_text_field( (string) ( $atts['cidade'] ?? '' ) ),
			'destaque'  => sanitize_text_field( (string) ( $atts['destaque'] ?? '' ) ),
			'page'      => 1,
			'per_page'  => $atts['per_page'],
		);

		$data = self::query_partners( $query_args );

		ob_start();
		?>
		<div class="serben-listing" data-serben-listing data-defaults="<?php echo esc_attr( wp_json_encode( $query_args ) ); ?>">
			<?php if ( $show_filters ) : ?>
				<?php $this->render_filters( $query_args ); ?>
			<?php endif; ?>
			<div class="serben-results-meta"><span data-serben-count><?php echo esc_html( sprintf( _n( '%s parceiro encontrado', '%s parceiros encontrados', $data['total'], 'serben-clube-beneficios' ), number_format_i18n( $data['total'] ) ) ); ?></span></div>
			<div class="serben-grid-public" data-serben-results><?php echo wp_kses_post( $data['html'] ); ?></div>
			<div class="serben-pagination" data-serben-pagination><?php echo wp_kses_post( $data['pagination'] ); ?></div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_filters( array $query_args ): void {
		$terms     = $this->get_terms();
		$cities    = Serben_Branches::cities();
		$filter_id = wp_unique_id( 'serben-filter-' );
		?>
		<form class="serben-filters" data-serben-filters>
			<div class="serben-filter-field serben-search-box">
				<label for="<?php echo esc_attr( $filter_id . '-search' ); ?>"><?php esc_html_e( 'Buscar parceiro', 'serben-clube-beneficios' ); ?></label>
				<input id="<?php echo esc_attr( $filter_id . '-search' ); ?>" type="search" name="s" placeholder="<?php esc_attr_e( 'Nome, desconto ou descrição...', 'serben-clube-beneficios' ); ?>" autocomplete="off">
			</div>

			<div class="serben-filter-field">
				<label for="<?php echo esc_attr( $filter_id . '-category' ); ?>"><?php esc_html_e( 'Categoria', 'serben-clube-beneficios' ); ?></label>
				<select id="<?php echo esc_attr( $filter_id . '-category' ); ?>" name="categoria">
					<option value=""><?php esc_html_e( 'Todas', 'serben-clube-beneficios' ); ?></option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $query_args['categoria'], $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="serben-filter-field">
				<label for="<?php echo esc_attr( $filter_id . '-city' ); ?>"><?php esc_html_e( 'Cidade', 'serben-clube-beneficios' ); ?></label>
				<select id="<?php echo esc_attr( $filter_id . '-city' ); ?>" name="cidade">
					<option value=""><?php esc_html_e( 'Todas', 'serben-clube-beneficios' ); ?></option>
					<?php foreach ( $cities as $city ) : ?>
						<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $query_args['cidade'], $city ); ?>><?php echo esc_html( $city ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="serben-filter-field">
				<label for="<?php echo esc_attr( $filter_id . '-type' ); ?>"><?php esc_html_e( 'Atendimento', 'serben-clube-beneficios' ); ?></label>
				<select id="<?php echo esc_attr( $filter_id . '-type' ); ?>" name="tipo">
					<option value=""><?php esc_html_e( 'Todos', 'serben-clube-beneficios' ); ?></option>
					<option value="fisico" <?php selected( $query_args['tipo'], 'fisico' ); ?>><?php esc_html_e( 'Físico', 'serben-clube-beneficios' ); ?></option>
					<option value="online" <?php selected( $query_args['tipo'], 'online' ); ?>><?php esc_html_e( 'Online', 'serben-clube-beneficios' ); ?></option>
				</select>
			</div>

			<button type="reset" class="serben-filter-reset"><?php esc_html_e( 'Limpar filtros', 'serben-clube-beneficios' ); ?></button>
			<input type="hidden" name="per_page" value="<?php echo esc_attr( $query_args['per_page'] ); ?>">
		</form>
		<?php
	}

	private function get_terms(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => SERBEN_TAXONOMY,
				'hide_empty' => false,
				'parent'     => 0,
				'meta_key'   => SERBEN_META_PREFIX . 'order',
				'orderby'    => 'meta_value_num',
				'order'      => 'ASC',
			)
		);
		return is_wp_error( $terms ) ? array() : $terms;
	}

	public static function query_partners( array $args ): array {
		$args = wp_parse_args(
			$args,
			array(
				's'         => '',
				'categoria' => '',
				'tipo'      => '',
				'cidade'    => '',
				'destaque'  => '',
				'page'      => 1,
				'per_page'  => 12,
			)
		);

		$args['page']     = max( 1, absint( $args['page'] ) );
		$args['per_page'] = max( 1, min( 48, absint( $args['per_page'] ) ) );
		$args['tipo']     = in_array( $args['tipo'], array( 'fisico', 'online' ), true ) ? $args['tipo'] : '';
		$args['s']        = sanitize_text_field( $args['s'] );
		$args['cidade']   = sanitize_text_field( $args['cidade'] );
		$args['categoria'] = sanitize_title( $args['categoria'] );

		$cache_key = 'serben_query_' . md5( wp_json_encode( $args ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query_args = array(
			'post_type'      => SERBEN_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $args['per_page'],
			'paged'          => $args['page'],
			's'              => $args['s'],
			'orderby'        => array( 'meta_value_num' => 'DESC', 'title' => 'ASC' ),
			'meta_key'       => SERBEN_META_PREFIX . 'destaque',
			'no_found_rows'  => false,
		);

		$meta_query = array();
		if ( $args['tipo'] ) {
			$meta_query[] = array(
				'key'   => SERBEN_META_PREFIX . 'tipo',
				'value' => $args['tipo'],
			);
		}
		if ( '' !== (string) $args['destaque'] ) {
			$meta_query[] = array(
				'key'   => SERBEN_META_PREFIX . 'destaque',
				'value' => '1',
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
					'terms'    => $args['categoria'],
				),
			);
		}

		if ( $args['cidade'] ) {
			$ids = Serben_Branches::partner_ids_by_city( $args['cidade'] );
			$query_args['post__in'] = $ids ?: array( 0 );
		}

		$q = new WP_Query( $query_args );

		$html = '';
		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$html .= self::render_card( get_the_ID() );
			}
			wp_reset_postdata();
		} else {
			$html = '<div class="serben-empty">' . esc_html__( 'Nenhum parceiro encontrado.', 'serben-clube-beneficios' ) . '</div>';
		}

		$result = array(
			'html'       => $html,
			'total'      => absint( $q->found_posts ),
			'pages'      => absint( $q->max_num_pages ),
			'pagination' => self::pagination( $args['page'], absint( $q->max_num_pages ) ),
		);
		set_transient( $cache_key, $result, 15 * MINUTE_IN_SECONDS );
		return $result;
	}

    public static function render_card( int $post_id, bool $force_brand = false ): string {
        $tipo = (string) get_post_meta( $post_id, SERBEN_META_PREFIX . 'tipo', true );
        if ( '' === $tipo ) $tipo = (string) get_post_meta( $post_id, 'tipo', true );
        if ( absint( get_post_meta( $post_id, 'awin_advertiser_id', true ) ) > 0 ) $tipo = 'online';
        $tipo = strtolower( remove_accents( trim( $tipo ) ) );
        $online = false !== strpos( $tipo, 'online' ) || false !== strpos( $tipo, 'virtual' ) || false !== strpos( $tipo, 'ecommerce' );

        $name = get_the_title( $post_id );
        $permalink = get_permalink( $post_id );
        $logo = class_exists( 'Serben_Utils' ) ? Serben_Utils::partner_logo_url( $post_id, 'large' ) : '';
        $cover = class_exists( 'Serben_Cover_Studio' ) ? Serben_Cover_Studio::public_cover_url( $post_id, 'large' ) : '';
        $card_cover = ( ! $online && $cover ) ? $cover : '';
        $brand_profile = class_exists( 'Serben_Utils' ) ? Serben_Utils::brand_profile( $post_id, $name ) : array( 'color' => '#2C2730', 'foreground' => '#FFFFFF', 'source' => 'neutral' );
        $brand = (string) ( $brand_profile['color'] ?? '#2C2730' );
        $brand_fg = (string) ( $brand_profile['foreground'] ?? '#FFFFFF' );
        $brand_source = sanitize_html_class( (string) ( $brand_profile['source'] ?? 'neutral' ) );
        $benefit = class_exists( 'Serben_Utils' ) ? Serben_Utils::benefit_text( $post_id, $online ) : ( $online ? 'Vantagem exclusiva na loja online' : 'Vantagem exclusiva para associados' );

        $initials = '';
        foreach ( preg_split( '/\s+/u', trim( $name ) ) as $word ) {
            if ( '' === $word ) continue;
            $initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1, 'UTF-8' ) : substr( $word, 0, 1 );
            if ( ( function_exists( 'mb_strlen' ) ? mb_strlen( $initials, 'UTF-8' ) : strlen( $initials ) ) >= 2 ) break;
        }
        $initials = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials, 'UTF-8' ) : strtoupper( $initials );

        $visual_style = '--brand-bg:' . $brand . ';--brand-fg:' . $brand_fg . ';';
        if ( $card_cover ) $visual_style .= '--media-image:url(\'' . esc_url( $card_cover ) . '\');';

        ob_start(); ?>
        <article class="serben-mini-card<?php echo $card_cover ? ' has-cover' : ' has-logo'; ?>">
            <a class="serben-mini-card__surface" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( 'Ver oferta de ' . $name ); ?>">
                <span class="serben-mini-card__visual<?php echo $card_cover ? ' is-cover' : ' is-logo is-brand-' . esc_attr( $brand_source ); ?>" style="<?php echo esc_attr( $visual_style ); ?>">
                    <?php if ( $card_cover ) : ?>
                        <img class="serben-mini-card__cover" src="<?php echo esc_url( $card_cover ); ?>" alt="<?php echo esc_attr( 'Imagem de ' . $name ); ?>" loading="lazy" decoding="async">
                    <?php elseif ( $logo ) : ?>
                        <img class="serben-mini-card__brand-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( 'Logo ' . $name ); ?>" loading="lazy" decoding="async">
                    <?php else : ?>
                        <i class="serben-mini-card__initials"><?php echo esc_html( $initials ?: 'CS' ); ?></i>
                    <?php endif; ?>
                </span>
                <span class="serben-mini-card__copy">
                    <span class="serben-mini-card__brandline">
                        <?php if ( $card_cover && $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( 'Logo ' . $name ); ?>" loading="lazy" decoding="async"><?php endif; ?>
                        <strong class="serben-mini-card__name"><?php echo esc_html( $name ); ?></strong>
                    </span>
                    <span class="serben-mini-card__benefit"><?php echo esc_html( wp_trim_words( $benefit, 13, '…' ) ); ?></span>
                    <b><?php esc_html_e( 'Ver oferta', 'serben-clube-beneficios' ); ?> <?php echo Serben_Utils::fa_icon( 'arrow-right', 'serben-mini-card__arrow' ); ?></b>
                </span>
            </a>
        </article>
        <?php return ob_get_clean();
    }

	private static function pagination( int $current, int $max ): string {
		if ( $max <= 1 ) {
			return '';
		}
		$html = '';
		for ( $i = 1; $i <= $max; $i++ ) {
			$html .= sprintf(
				'<button type="button" class="serben-page %s" data-page="%d">%s</button>',
				$current === $i ? 'is-active' : '',
				$i,
				esc_html( (string) $i )
			);
		}
		return $html;
	}
}
