<?php
/**
 * Club Serben — Mídia de Parceiros.
 * Gerencia capa editorial real, origem/crédito e fallback visual.
 */
defined( 'ABSPATH' ) || exit;

final class Serben_Cover_Studio {
    const META_COVER_ID     = '_serben_cover_id';
    const META_SOURCE_URL   = '_serben_cover_source_url';
    const META_SOURCE_LABEL = '_serben_cover_source_label';
    const META_CREDIT       = '_serben_cover_credit';
    const META_QUERY        = '_serben_cover_query';
    const META_URL          = '_serben_auto_banner_url';
    const META_PATH         = '_serben_auto_banner_path';
    const META_CATEGORY     = '_serben_auto_banner_category';
    const META_VERSION      = '_serben_auto_banner_layout_version';
    const VERSION           = '1.8.2';
    const META_BULK_STATUS  = '_serben_cover_bulk_status';
    const META_BULK_DETAIL  = '_serben_cover_bulk_detail';
    const BULK_OPTION       = 'serben_cover_bulk_job';
    const BACKGROUND_URL    = 'https://bumemania.com.br/clubserben/wp-content/uploads/2026/09/BG-Capa-Parceiros.png';

    public function init(): void {
        add_action( 'add_meta_boxes', array( $this, 'box' ) );
        add_action( 'admin_post_serben_regenerate_cover', array( $this, 'regenerate' ) );
        add_action( 'admin_post_serben_regenerate_all_covers', array( $this, 'regenerate_all' ) );
        add_action( 'admin_post_serben_import_cover_url', array( $this, 'import_cover_url' ) );
        add_action( 'admin_post_serben_fetch_official_cover', array( $this, 'fetch_official_cover' ) );
        add_action( 'admin_post_serben_remove_editorial_cover', array( $this, 'remove_editorial_cover' ) );
        add_action( 'admin_post_serben_bulk_official_covers', array( $this, 'start_bulk_official_covers' ) );
        add_action( 'serben_cover_bulk_batch', array( $this, 'run_bulk_batch' ) );
        add_action( 'save_post_' . SERBEN_POST_TYPE, array( $this, 'on_save' ), 60, 3 );
        add_action( 'set_object_terms', array( $this, 'on_terms' ), 60, 6 );
    }

    private static function len( string $s ): int { return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s ); }
    private static function sub( string $s, int $a, int $l = 0 ): string { return function_exists( 'mb_substr' ) ? mb_substr( $s, $a, $l ?: null, 'UTF-8' ) : ( $l ? substr( $s, $a, $l ) : substr( $s, $a ) ); }
    private static function upper( string $s ): string { return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $s, 'UTF-8' ) : strtoupper( $s ); }
    private static function xml( string $s ): string { return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' ); }

    public function box(): void {
        add_meta_box( 'serben_cover_studio', __( 'Mídia do parceiro', 'serben-clube-beneficios' ), array( $this, 'render_box' ), SERBEN_POST_TYPE, 'side', 'high' );
    }

    private static function official_site( int $id ): string {
        foreach ( array( SERBEN_META_PREFIX . 'site', 'site', 'website', 'url' ) as $key ) {
            $url = trim( (string) get_post_meta( $id, $key, true ) );
            if ( $url && wp_http_validate_url( $url ) ) return esc_url_raw( $url );
        }
        return '';
    }

    public static function search_query( int $id ): string {
        $saved = trim( (string) get_post_meta( $id, self::META_QUERY, true ) );
        if ( $saved !== '' ) return $saved;
        $cat = self::category( $id );
        return trim( get_the_title( $id ) . ' ' . $cat['name'] . ' produtos marca oficial divulgação' );
    }

    public function render_box( WP_Post $post ): void {
        $id = $post->ID;
        $url = self::cover_url( $id );
        $editorial = self::editorial_url( $id );
        $site = self::official_site( $id );
        $query = self::search_query( $id );
        $source = trim( (string) get_post_meta( $id, self::META_SOURCE_LABEL, true ) );
        $credit = trim( (string) get_post_meta( $id, self::META_CREDIT, true ) );
        $source_url = trim( (string) get_post_meta( $id, self::META_SOURCE_URL, true ) );
        $google = 'https://www.google.com/search?tbm=isch&q=' . rawurlencode( $query );
        $regen = wp_nonce_url( admin_url( 'admin-post.php?action=serben_regenerate_cover&post_id=' . $id ), 'serben_regenerate_cover_' . $id );
        $fetch = wp_nonce_url( admin_url( 'admin-post.php?action=serben_fetch_official_cover&post_id=' . $id ), 'serben_fetch_official_cover_' . $id );
        $remove = wp_nonce_url( admin_url( 'admin-post.php?action=serben_remove_editorial_cover&post_id=' . $id ), 'serben_remove_editorial_cover_' . $id );

        echo '<div class="serben-cover-box">';
        if ( $url ) echo '<p><img src="' . esc_url( $url ) . '" alt="" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:12px;border:1px solid #e5dce8"></p>';
        echo '<p><strong>Capa ativa:</strong> ' . ( $editorial ? 'Editorial / marca' : 'Fallback Club Serben' ) . '</p>';
        if ( $source || $credit ) echo '<p class="description">' . esc_html( $credit ?: $source ) . '</p>';
        if ( $source_url ) echo '<p><a href="' . esc_url( $source_url ) . '" target="_blank" rel="noopener">Ver fonte registrada</a></p>';
        echo '<hr><p><strong>Busca sugerida</strong><br><code style="white-space:normal">' . esc_html( $query ) . '</code></p>';
        echo '<p><a class="button" href="' . esc_url( $google ) . '" target="_blank" rel="noopener">Buscar imagens no Google</a></p>';
        if ( $site ) echo '<p><a class="button button-secondary" href="' . esc_url( $fetch ) . '">Buscar capa no site oficial</a></p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'serben_import_cover_url_' . $id );
        echo '<input type="hidden" name="action" value="serben_import_cover_url"><input type="hidden" name="post_id" value="' . absint( $id ) . '">';
        echo '<p><label><strong>URL direta da imagem</strong></label><input type="url" name="image_url" class="widefat" placeholder="https://..."></p>';
        echo '<p><label><strong>URL da página de origem</strong></label><input type="url" name="source_url" class="widefat" placeholder="https://..."></p>';
        echo '<p><label><strong>Crédito</strong></label><input type="text" name="credit" class="widefat" value="' . esc_attr( $credit ?: 'Imagem de divulgação da marca' ) . '"></p>';
        echo '<p><button class="button button-primary" type="submit">Importar e usar como capa</button></p></form>';
        echo '<p class="description">Use imagem oficial ou cuja utilização esteja autorizada. O Google serve para descoberta; registre sempre a página original.</p>';
        if ( $editorial ) echo '<p><a class="button-link-delete" href="' . esc_url( $remove ) . '">Remover capa editorial</a></p>';
        echo '<p><a class="button-link" href="' . esc_url( $regen ) . '">Regenerar fallback visual</a></p></div>';
    }

    private static function redirect( int $id, string $msg ): void {
        wp_safe_redirect( add_query_arg( array( 'post' => $id, 'action' => 'edit', 'serben_cover_notice' => rawurlencode( $msg ) ), admin_url( 'post.php' ) ) );
        exit;
    }

    public function import_cover_url(): void {
        $id = absint( $_POST['post_id'] ?? 0 );
        if ( ! $id || SERBEN_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_die( 'Permissão insuficiente.' );
        check_admin_referer( 'serben_import_cover_url_' . $id );
        $image_url = esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) );
        $source_url = esc_url_raw( wp_unslash( $_POST['source_url'] ?? '' ) );
        $credit = sanitize_text_field( wp_unslash( $_POST['credit'] ?? '' ) );
        if ( ! wp_http_validate_url( $image_url ) ) self::redirect( $id, 'Informe uma URL de imagem válida.' );
        $result = self::sideload_cover( $id, $image_url, $source_url, $credit ?: 'Imagem de divulgação da marca', 'Fonte informada' );
        self::redirect( $id, is_wp_error( $result ) ? $result->get_error_message() : 'Capa importada e ativada com sucesso.' );
    }

    public function fetch_official_cover(): void {
        $id = absint( $_GET['post_id'] ?? 0 );
        if ( ! $id || SERBEN_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_die( 'Permissão insuficiente.' );
        check_admin_referer( 'serben_fetch_official_cover_' . $id );
        $site = self::official_site( $id );
        if ( ! $site ) self::redirect( $id, 'Cadastre primeiro o site oficial do parceiro.' );
        $image = self::discover_official_image( $site, $id );
        if ( is_wp_error( $image ) ) self::redirect( $id, $image->get_error_message() );
        $result = self::sideload_cover(
            $id,
            (string) $image['url'],
            (string) $image['source_url'],
            'Imagem de divulgação da marca',
            'Site oficial da marca'
        );
        if ( ! is_wp_error( $result ) ) {
            update_post_meta( $id, self::META_BULK_DETAIL, sanitize_text_field( $image['method'] . ' · confiança ' . absint( $image['score'] ) ) );
        }
        self::redirect( $id, is_wp_error( $result ) ? $result->get_error_message() : 'Imagem oficial encontrada, importada e ativada.' );
    }

    private static function discover_official_image( string $site, int $post_id = 0 ) {
        $site = self::normalize_http_url( $site );
        if ( ! $site ) return new WP_Error( 'invalid_site', 'O endereço do site oficial é inválido.' );

        $brand = $post_id ? get_the_title( $post_id ) : '';
        $category = $post_id ? self::category( $post_id )['name'] : '';
        $pages = array( $site );
        $visited = array();
        $candidates = array();
        $last_error = '';

        /*
         * Primeira camada: página oficial principal. Dela extraímos OG/Twitter,
         * JSON-LD, imagens reais e alguns links internos semanticamente úteis.
         */
        for ( $cursor = 0; $cursor < count( $pages ) && $cursor < 6; $cursor++ ) {
            $page_url = $pages[$cursor];
            if ( isset( $visited[$page_url] ) ) continue;
            $visited[$page_url] = true;

            $response = self::fetch_html( $page_url );
            if ( is_wp_error( $response ) ) {
                $last_error = $response->get_error_message();
                continue;
            }

            $html = $response['body'];
            foreach ( self::extract_image_candidates( $html, $page_url, $brand, $category ) as $candidate ) {
                $key = $candidate['url'];
                if ( ! isset( $candidates[$key] ) || $candidate['score'] > $candidates[$key]['score'] ) {
                    $candidates[$key] = $candidate;
                }
            }

            /* Na home, descobrimos páginas de produto/loja/coleções. */
            if ( 0 === $cursor ) {
                foreach ( self::extract_internal_pages( $html, $page_url ) as $internal ) {
                    if ( count( $pages ) >= 5 ) break;
                    if ( ! in_array( $internal, $pages, true ) ) $pages[] = $internal;
                }

                /* Sitemap é uma camada extra e limitada: prioriza URLs com imagem/produto. */
                foreach ( self::sitemap_pages( $site ) as $map_page ) {
                    if ( count( $pages ) >= 6 ) break;
                    if ( ! in_array( $map_page, $pages, true ) ) $pages[] = $map_page;
                }
            }
        }

        if ( ! $candidates ) {
            return new WP_Error( 'image', $last_error ?: 'Nenhuma imagem editorial compatível foi localizada no site oficial.' );
        }

        usort( $candidates, static function( $a, $b ) {
            return (int) $b['score'] <=> (int) $a['score'];
        } );

        foreach ( array_slice( $candidates, 0, 12 ) as $candidate ) {
            if ( $candidate['score'] < 48 ) continue;
            if ( self::remote_image_looks_valid( $candidate['url'] ) ) return $candidate;
        }

        return new WP_Error( 'image', 'Foram encontradas imagens no site, mas nenhuma passou na validação de capa.' );
    }

    private static function fetch_html( string $url ) {
        $response = wp_safe_remote_get( $url, array(
            'timeout'     => 10,
            'redirection' => 3,
            'user-agent'  => 'Mozilla/5.0 (compatible; ClubSerbenMedia/1.8.2; +https://bumemania.com.br/clubserben/)',
            'headers'     => array( 'Accept' => 'text/html,application/xhtml+xml' ),
        ) );
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'request', 'Falha ao acessar o site: ' . $response->get_error_message() );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 ) return new WP_Error( 'http_' . $code, 'O site respondeu com HTTP ' . $code . '.' );
        $content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
        if ( $content_type && false === strpos( $content_type, 'text/html' ) && false === strpos( $content_type, 'xhtml' ) ) {
            return new WP_Error( 'content_type', 'A página retornada não é HTML.' );
        }
        $body = (string) wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > 3 * MB_IN_BYTES ) $body = substr( $body, 0, 3 * MB_IN_BYTES );
        if ( trim( $body ) === '' ) return new WP_Error( 'empty', 'O site retornou uma página vazia.' );
        return array( 'body' => $body, 'code' => $code );
    }

    private static function normalize_http_url( string $url ): string {
        $url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
        if ( strpos( $url, '//' ) === 0 ) $url = 'https:' . $url;
        if ( ! preg_match( '#^https?://#i', $url ) ) return '';
        return wp_http_validate_url( $url ) ? esc_url_raw( $url ) : '';
    }

    private static function absolute_url( string $base, string $url ): string {
        $url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );
        if ( $url === '' || preg_match( '#^(data:|javascript:|mailto:|tel:)#i', $url ) ) return '';
        if ( preg_match( '#^https?://#i', $url ) ) return self::normalize_http_url( $url );
        $p = wp_parse_url( $base );
        if ( empty( $p['host'] ) ) return '';
        $scheme = $p['scheme'] ?? 'https';
        if ( strpos( $url, '//' ) === 0 ) return self::normalize_http_url( $scheme . ':' . $url );
        if ( strpos( $url, '/' ) === 0 ) return self::normalize_http_url( $scheme . '://' . $p['host'] . $url );
        $path = isset( $p['path'] ) ? preg_replace( '#/[^/]*$#', '/', $p['path'] ) : '/';
        return self::normalize_http_url( $scheme . '://' . $p['host'] . $path . $url );
    }

    private static function same_host( string $a, string $b ): bool {
        $ha = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( $a, PHP_URL_HOST ) ) );
        $hb = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( $b, PHP_URL_HOST ) ) );
        return $ha !== '' && $ha === $hb;
    }

    private static function add_candidate( array &$out, string $raw, string $page, int $score, string $method, string $context = '', string $brand = '', string $category = '' ): void {
        /* srcset: usa o último item, normalmente a maior resolução. */
        if ( false !== strpos( $raw, ',' ) && preg_match( '/\s\d+[wx](?:\s*,|$)/', $raw ) ) {
            $parts = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
            $last = end( $parts );
            $raw = preg_replace( '/\s+\d+(?:w|x)$/', '', (string) $last );
        }
        $url = self::absolute_url( $page, $raw );
        if ( ! $url ) return;

        $haystack = strtolower( remove_accents( $url . ' ' . $context ) );
        $bad = array( 'logo', 'favicon', 'icon-', '/icon', 'sprite', 'placeholder', 'avatar', 'payment', 'pagamento', 'footer', 'header-logo', 'seal', 'selo', 'loading', 'spinner', 'pixel', 'tracking', '1x1', 'blank.' );
        foreach ( $bad as $needle ) if ( false !== strpos( $haystack, $needle ) ) $score -= 90;

        $good = array( 'product', 'produto', 'shop', 'loja', 'collection', 'colecao', 'catalog', 'catalogo', 'campaign', 'campanha', 'banner', 'hero', 'featured', 'vitrine' );
        foreach ( $good as $needle ) if ( false !== strpos( $haystack, $needle ) ) $score += 7;

        foreach ( array( $brand, $category ) as $term ) {
            $term = strtolower( remove_accents( trim( $term ) ) );
            if ( $term && false !== strpos( $haystack, $term ) ) $score += 8;
        }

        if ( preg_match( '/\.(jpe?g|png|webp)(?:\?|$)/i', $url ) ) $score += 4;
        if ( preg_match( '/(?:width|w)[=_-]?(?:1[2-9]\d\d|[2-9]\d{3,})/i', $url ) ) $score += 5;
        if ( $score < 1 ) return;

        $out[] = array(
            'url'        => $url,
            'source_url' => $page,
            'score'      => $score,
            'method'     => $method,
        );
    }

    private static function extract_image_candidates( string $html, string $page, string $brand = '', string $category = '' ): array {
        $out = array();

        $meta_patterns = array(
            array( '/<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']/i', 92, 'Open Graph' ),
            array( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']/i', 92, 'Open Graph' ),
            array( '/<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']/i', 88, 'Twitter Card' ),
            array( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image(?::src)?["\']/i', 88, 'Twitter Card' ),
        );
        foreach ( $meta_patterns as $spec ) {
            if ( preg_match_all( $spec[0], $html, $matches ) ) {
                foreach ( $matches[1] as $raw ) self::add_candidate( $out, $raw, $page, $spec[1], $spec[2], '', $brand, $category );
            }
        }

        /* JSON-LD: imagens de Product têm prioridade sobre Organization/WebSite. */
        if ( preg_match_all( '#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks ) ) {
            foreach ( $blocks[1] as $block ) {
                $json = json_decode( html_entity_decode( trim( $block ), ENT_QUOTES, 'UTF-8' ), true );
                if ( ! is_array( $json ) ) continue;
                self::jsonld_images( $json, $out, $page, $brand, $category );
            }
        }

        /* IMG/Picture lazy-load: limita para não transformar a busca em crawler pesado. */
        if ( preg_match_all( '#<img\b([^>]*)>#is', $html, $imgs ) ) {
            $seen = 0;
            foreach ( $imgs[1] as $attrs ) {
                if ( $seen++ >= 80 ) break;
                $context = '';
                if ( preg_match( '/(?:alt|title)=["\']([^"\']*)["\']/i', $attrs, $m ) ) $context .= ' ' . $m[1];
                foreach ( array( 'data-srcset', 'srcset', 'data-lazy-src', 'data-original', 'data-src', 'src' ) as $attr ) {
                    if ( preg_match( '/\b' . preg_quote( $attr, '/' ) . '=["\']([^"\']+)["\']/i', $attrs, $m ) ) {
                        self::add_candidate( $out, $m[1], $page, 48, 'Imagem da página', $context, $brand, $category );
                        break;
                    }
                }
            }
        }

        /* Alguns e-commerces usam imagem de fundo para vitrines/heroes. */
        if ( preg_match_all( '/url\((["\']?)(https?:\/\/|\/)([^)"\']+)\1\)/i', $html, $css, PREG_SET_ORDER ) ) {
            foreach ( array_slice( $css, 0, 30 ) as $m ) {
                self::add_candidate( $out, $m[2] . $m[3], $page, 44, 'Imagem de fundo', '', $brand, $category );
            }
        }

        return $out;
    }

    private static function jsonld_images( $node, array &$out, string $page, string $brand, string $category, string $type = '' ): void {
        if ( ! is_array( $node ) ) return;
        if ( isset( $node['@type'] ) ) {
            $raw_type = is_array( $node['@type'] ) ? implode( ' ', $node['@type'] ) : (string) $node['@type'];
            $type = strtolower( $raw_type );
        }
        if ( isset( $node['image'] ) ) {
            $score = false !== strpos( $type, 'product' ) ? 100 : ( false !== strpos( $type, 'offer' ) ? 94 : 76 );
            $images = is_array( $node['image'] ) ? $node['image'] : array( $node['image'] );
            foreach ( $images as $image ) {
                if ( is_string( $image ) ) self::add_candidate( $out, $image, $page, $score, 'Dados estruturados', $type, $brand, $category );
                elseif ( is_array( $image ) && ! empty( $image['url'] ) ) self::add_candidate( $out, (string) $image['url'], $page, $score, 'Dados estruturados', $type, $brand, $category );
            }
        }
        foreach ( $node as $value ) if ( is_array( $value ) ) self::jsonld_images( $value, $out, $page, $brand, $category, $type );
    }

    private static function extract_internal_pages( string $html, string $base ): array {
        $ranked = array();
        if ( ! preg_match_all( '#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', $html, $links, PREG_SET_ORDER ) ) return array();
        foreach ( array_slice( $links, 0, 250 ) as $m ) {
            $url = self::absolute_url( $base, $m[1] );
            if ( ! $url || ! self::same_host( $base, $url ) ) continue;
            $text = strtolower( remove_accents( wp_strip_all_tags( $m[2] ) . ' ' . $url ) );
            $score = 0;
            foreach ( array( 'produto'=>25, 'products'=>25, 'loja'=>24, 'shop'=>24, 'colecao'=>22, 'collection'=>22, 'catalogo'=>20, 'catalog'=>20, 'novidade'=>16, 'lancamento'=>16, 'oferta'=>14, 'promo'=>14 ) as $needle=>$points ) {
                if ( false !== strpos( $text, $needle ) ) $score += $points;
            }
            if ( $score > 0 ) $ranked[$url] = max( $ranked[$url] ?? 0, $score );
        }
        arsort( $ranked );
        return array_slice( array_keys( $ranked ), 0, 4 );
    }

    private static function sitemap_pages( string $site ): array {
        $p = wp_parse_url( $site );
        if ( empty( $p['host'] ) ) return array();
        $root = ( $p['scheme'] ?? 'https' ) . '://' . $p['host'];
        $urls = array( $root . '/sitemap.xml', $root . '/wp-sitemap.xml' );
        $pages = array();
        foreach ( $urls as $map ) {
            $r = wp_safe_remote_get( $map, array( 'timeout'=>6, 'redirection'=>2, 'user-agent'=>'ClubSerbenMedia/1.8.2' ) );
            if ( is_wp_error( $r ) || (int) wp_remote_retrieve_response_code( $r ) >= 400 ) continue;
            $xml = (string) wp_remote_retrieve_body( $r );
            if ( strlen( $xml ) > MB_IN_BYTES ) $xml = substr( $xml, 0, MB_IN_BYTES );
            if ( preg_match_all( '#<loc>\s*([^<]+)\s*</loc>#i', $xml, $matches ) ) {
                foreach ( $matches[1] as $loc ) {
                    $loc = html_entity_decode( trim( $loc ), ENT_QUOTES, 'UTF-8' );
                    if ( ! self::same_host( $site, $loc ) ) continue;
                    $low = strtolower( remove_accents( $loc ) );
                    if ( preg_match( '#product|produto|shop|loja|collection|colecao|categoria|category#', $low ) ) $pages[] = esc_url_raw( $loc );
                    if ( count( $pages ) >= 2 ) break 2;
                }
            }
        }
        return array_values( array_unique( $pages ) );
    }

    private static function remote_image_looks_valid( string $url ): bool {
        $response = wp_safe_remote_head( $url, array( 'timeout'=>6, 'redirection'=>3, 'user-agent'=>'ClubSerbenMedia/1.8.2' ) );
        if ( is_wp_error( $response ) ) return true; // alguns CDNs bloqueiam HEAD, mas aceitam GET no sideload.
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code >= 400 && $code !== 405 ) return false;
        $type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
        if ( $type && false === strpos( $type, 'image/' ) ) return false;
        $length = (int) wp_remote_retrieve_header( $response, 'content-length' );
        if ( $length > 0 && $length < 12000 ) return false;
        return true;
    }

    private static function sideload_cover( int $id, string $image_url, string $source_url, string $credit, string $source_label ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_sideload_image( $image_url, $id, get_the_title( $id ) . ' — capa', 'id' );
        if ( is_wp_error( $attachment_id ) ) return $attachment_id;
        $mime = (string) get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
            wp_delete_attachment( $attachment_id, true );
            return new WP_Error( 'mime', 'O arquivo retornado não é uma imagem compatível.' );
        }
        update_post_meta( $id, self::META_COVER_ID, absint( $attachment_id ) );
        update_post_meta( $id, self::META_SOURCE_URL, esc_url_raw( $source_url ) );
        update_post_meta( $id, self::META_SOURCE_LABEL, sanitize_text_field( $source_label ) );
        update_post_meta( $id, self::META_CREDIT, sanitize_text_field( $credit ) );
        return $attachment_id;
    }

    public function remove_editorial_cover(): void {
        $id = absint( $_GET['post_id'] ?? 0 );
        if ( ! $id || SERBEN_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_die( 'Permissão insuficiente.' );
        check_admin_referer( 'serben_remove_editorial_cover_' . $id );
        delete_post_meta( $id, self::META_COVER_ID );
        delete_post_meta( $id, self::META_SOURCE_URL );
        delete_post_meta( $id, self::META_SOURCE_LABEL );
        delete_post_meta( $id, self::META_CREDIT );
        self::redirect( $id, 'Capa editorial removida. O fallback Club Serben voltou a ser usado.' );
    }

    public static function editorial_url( int $id, string $size = 'full' ): string {
        $cover_id = absint( get_post_meta( $id, self::META_COVER_ID, true ) );
        if ( $cover_id ) {
            $url = wp_get_attachment_image_url( $cover_id, $size );
            if ( $url ) return (string) $url;
        }
        $manual = absint( get_post_meta( $id, SERBEN_META_PREFIX . 'banner_id', true ) );
        if ( $manual ) {
            $url = wp_get_attachment_image_url( $manual, $size );
            if ( $url ) return (string) $url;
        }
        return '';
    }

    public static function cover_credit( int $id ): string {
        return trim( (string) get_post_meta( $id, self::META_CREDIT, true ) );
    }

    public function regenerate(): void {
        $id = absint( $_GET['post_id'] ?? 0 );
        if ( ! $id || SERBEN_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) wp_die( 'Permissão insuficiente.' );
        check_admin_referer( 'serben_regenerate_cover_' . $id );
        $r = self::generate( $id, true );
        self::redirect( $id, is_wp_error( $r ) ? $r->get_error_message() : 'Fallback visual regenerado.' );
    }


    /**
     * Inicia a busca em lote. O processamento ocorre em pequenos lotes para
     * não estourar o tempo limite do painel do WordPress.
     */
    public function start_bulk_official_covers(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permissão insuficiente.' );
        check_admin_referer( 'serben_bulk_official_covers' );

        $force = ! empty( $_POST['replace_existing'] );
        $scope = sanitize_key( $_POST['scope'] ?? 'missing' );
        if ( ! in_array( $scope, array( 'missing', 'failed', 'not_found', 'all' ), true ) ) $scope = 'missing';

        $ids = get_posts( array(
            'post_type'      => SERBEN_POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $ids = array_values( array_filter( $ids, static function( $id ) use ( $force, $scope ) {
            $id = (int) $id;
            $status = (string) get_post_meta( $id, Serben_Cover_Studio::META_BULK_STATUS, true );
            if ( 'failed' === $scope ) return 'failed' === $status;
            if ( 'not_found' === $scope ) return 'not_found' === $status;
            if ( 'all' === $scope ) return true;
            return $force || ! Serben_Cover_Studio::editorial_url( $id );
        } ) );

        foreach ( $ids as $id ) {
            delete_post_meta( (int) $id, self::META_BULK_STATUS );
            delete_post_meta( (int) $id, self::META_BULK_DETAIL );
        }

        update_option( self::BULK_OPTION, array(
            'ids'       => array_map( 'absint', $ids ),
            'total'     => count( $ids ),
            'processed' => 0,
            'success'   => 0,
            'no_site'   => 0,
            'not_found' => 0,
            'failed'    => 0,
            'force'     => $force ? 1 : 0,
            'scope'     => $scope,
            'started'   => time(),
            'finished'  => 0,
        ), false );

        if ( ! $ids ) {
            $job = get_option( self::BULK_OPTION, array() );
            $job['finished'] = time();
            update_option( self::BULK_OPTION, $job, false );
            wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( 'Nenhum parceiro corresponde ao filtro selecionado.' ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-maintenance' ) ) );
            exit;
        }

        if ( ! wp_next_scheduled( 'serben_cover_bulk_batch' ) ) wp_schedule_single_event( time() + 1, 'serben_cover_bulk_batch' );
        $this->run_bulk_batch();
        wp_safe_redirect( add_query_arg( 'serben_notice', rawurlencode( 'Busca inteligente de capas iniciada. Atualize esta página para acompanhar.' ), admin_url( 'edit.php?post_type=' . SERBEN_POST_TYPE . '&page=serben-maintenance' ) ) );
        exit;
    }

    public function run_bulk_batch(): void {
        $job = get_option( self::BULK_OPTION, array() );
        if ( empty( $job['ids'] ) || ! is_array( $job['ids'] ) ) {
            if ( ! empty( $job ) && empty( $job['finished'] ) ) {
                $job['finished'] = time();
                update_option( self::BULK_OPTION, $job, false );
            }
            return;
        }

        /* A busca agora visita páginas internas; lote menor reduz timeout. */
        $batch = array_splice( $job['ids'], 0, 2 );
        foreach ( $batch as $id ) {
            $id = absint( $id );
            if ( ! $id || SERBEN_POST_TYPE !== get_post_type( $id ) ) {
                $job['failed']++; $job['processed']++; continue;
            }

            if ( empty( $job['force'] ) && 'all' !== ( $job['scope'] ?? '' ) && self::editorial_url( $id ) ) {
                update_post_meta( $id, self::META_BULK_STATUS, 'existing' );
                update_post_meta( $id, self::META_BULK_DETAIL, 'Capa existente preservada' );
                $job['success']++; $job['processed']++; continue;
            }

            $site = self::official_site( $id );
            if ( ! $site ) {
                update_post_meta( $id, self::META_BULK_STATUS, 'no_site' );
                update_post_meta( $id, self::META_BULK_DETAIL, 'Site oficial não cadastrado' );
                $job['no_site']++; $job['processed']++; continue;
            }

            $image = self::discover_official_image( $site, $id );
            if ( is_wp_error( $image ) ) {
                $code = (string) $image->get_error_code();
                $is_failure = 0 === strpos( $code, 'http_' ) || in_array( $code, array( 'request', 'invalid_site', 'empty', 'content_type' ), true );
                update_post_meta( $id, self::META_BULK_STATUS, $is_failure ? 'failed' : 'not_found' );
                update_post_meta( $id, self::META_BULK_DETAIL, sanitize_text_field( $image->get_error_message() ) );
                if ( $is_failure ) $job['failed']++; else $job['not_found']++;
                $job['processed']++;
                continue;
            }

            $result = self::sideload_cover(
                $id,
                (string) $image['url'],
                (string) $image['source_url'],
                'Imagem de divulgação da marca',
                'Site oficial da marca'
            );
            if ( is_wp_error( $result ) ) {
                update_post_meta( $id, self::META_BULK_STATUS, 'failed' );
                update_post_meta( $id, self::META_BULK_DETAIL, sanitize_text_field( 'Importação: ' . $result->get_error_message() ) );
                $job['failed']++;
            } else {
                update_post_meta( $id, self::META_BULK_STATUS, 'success' );
                update_post_meta( $id, self::META_BULK_DETAIL, sanitize_text_field( $image['method'] . ' · confiança ' . absint( $image['score'] ) ) );
                $job['success']++;
            }
            $job['processed']++;
        }

        if ( empty( $job['ids'] ) ) $job['finished'] = time();
        update_option( self::BULK_OPTION, $job, false );
        if ( empty( $job['finished'] ) && ! wp_next_scheduled( 'serben_cover_bulk_batch' ) ) wp_schedule_single_event( time() + 5, 'serben_cover_bulk_batch' );
    }

    public static function bulk_status(): array {
        $job = get_option( self::BULK_OPTION, array() );
        return is_array( $job ) ? $job : array();
    }

    public function regenerate_all(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permissão insuficiente.' );
        check_admin_referer( 'serben_regenerate_all_covers' );
        $ids = get_posts( array( 'post_type' => SERBEN_POST_TYPE, 'post_status' => array( 'publish','draft','pending','private' ), 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $ok=0; $bad=0; foreach ( $ids as $id ) { $r=self::generate((int)$id,true); is_wp_error($r)?$bad++:$ok++; }
        $msg=sprintf('Fallbacks processados: %d gerados; %d falhas.',$ok,$bad);
        wp_safe_redirect(add_query_arg('serben_notice',rawurlencode($msg),admin_url('edit.php?post_type='.SERBEN_POST_TYPE.'&page=serben-maintenance'))); exit;
    }

    public function on_save( int $id, WP_Post $post, bool $update ): void {
        if ( wp_is_post_revision( $id ) || wp_is_post_autosave( $id ) ) return;
        $cat=self::category($id); $stored=(string)get_post_meta($id,self::META_CATEGORY,true);
        if ( ! self::generated_url( $id ) || $stored !== $cat['slug'] ) self::generate( $id, true );
    }
    public function on_terms( int $id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old ): void {
        if ( SERBEN_TAXONOMY === $taxonomy && SERBEN_POST_TYPE === get_post_type( $id ) ) self::generate( $id, true );
    }
    public static function category( int $id ): array {
        $terms=wp_get_post_terms($id,SERBEN_TAXONOMY,array('orderby'=>'term_order','order'=>'ASC'));
        if(!is_wp_error($terms)&&!empty($terms)&&$terms[0] instanceof WP_Term) return array('name'=>$terms[0]->name,'slug'=>$terms[0]->slug);
        return array('name'=>'Benefícios','slug'=>'beneficios');
    }
    private static function logo_data( int $id ): string {
        return class_exists( 'Serben_Utils' ) ? Serben_Utils::partner_logo_url( $id, 'large' ) : '';
    }
    private static function name_lines( string $name ): array {
        $name=trim(wp_strip_all_tags($name)); if(self::len($name)<=24)return array($name);
        $words=preg_split('/\s+/u',$name);$lines=array('');
        foreach($words as $w){$i=count($lines)-1;$try=trim($lines[$i].' '.$w);if(self::len($try)>24&&$lines[$i]!==''&&count($lines)<2)$lines[]=$w;else$lines[$i]=$try;}
        if(isset($lines[1])&&self::len($lines[1])>30)$lines[1]=self::sub($lines[1],0,29).'…';return $lines;
    }
    public static function generated_url( int $id ): string {
        $url=(string)get_post_meta($id,self::META_URL,true);$path=(string)get_post_meta($id,self::META_PATH,true);$version=(string)get_post_meta($id,self::META_VERSION,true);
        return ($url&&$path&&file_exists($path)&&self::VERSION===$version)?esc_url_raw($url):'';
    }

    /**
     * Resolve a capa pública usada em TODOS os pontos do plugin.
     *
     * Prioridade:
     * 1. capa editorial / banner escolhido na Biblioteca;
     * 2. URLs legadas explicitamente cadastradas como capa;
     * 3. imagem destacada somente quando ela tem proporção de banner e não é o logo.
     *
     * Isso evita o problema em que a vitrine exibia uma imagem e a página individual
     * caía no fallback roxo por consultar fontes diferentes.
     */
    public static function public_cover_url( int $id, string $size = 'large' ): string {
        $editorial = self::editorial_url( $id, $size );
        if ( $editorial ) return esc_url_raw( $editorial );

        foreach ( array(
            SERBEN_META_PREFIX . 'banner_url',
            SERBEN_META_PREFIX . 'capa_url',
            'banner_url',
            'capa_url',
            'imagem_capa',
            'cover_url',
        ) as $meta_key ) {
            $candidate = trim( (string) get_post_meta( $id, $meta_key, true ) );
            if ( $candidate && wp_http_validate_url( $candidate ) ) {
                return esc_url_raw( $candidate );
            }
        }

        $thumb_id = absint( get_post_thumbnail_id( $id ) );
        $logo_id  = absint( get_post_meta( $id, SERBEN_META_PREFIX . 'logo_id', true ) );

        if ( $thumb_id && $thumb_id !== $logo_id ) {
            $metadata = wp_get_attachment_metadata( $thumb_id );
            $width    = is_array( $metadata ) ? absint( $metadata['width'] ?? 0 ) : 0;
            $height   = is_array( $metadata ) ? absint( $metadata['height'] ?? 0 ) : 0;
            $ratio    = $height > 0 ? $width / $height : 0;

            // Uma imagem destacada quadrada costuma ser logo. Só aceitamos formato de capa.
            if ( $width >= 640 && $height >= 280 && $ratio >= 1.25 ) {
                $url = wp_get_attachment_image_url( $thumb_id, $size );
                if ( $url ) return esc_url_raw( $url );
            }
        }

        return '';
    }

    public static function has_real_cover( int $id ): bool {
        return (bool) self::public_cover_url( $id, 'large' );
    }

    public static function cover_url( int $id ): string {
        return self::public_cover_url( $id, 'large' );
    }

    public static function generate( int $id, bool $force=false ) {
        $post=get_post($id);if(!$post||SERBEN_POST_TYPE!==$post->post_type)return new WP_Error('invalid','Parceiro inválido.');
        if(!$force&&self::generated_url($id))return self::generated_url($id);
        $up=wp_upload_dir();if(!empty($up['error']))return new WP_Error('upload',$up['error']);
        $dir=trailingslashit($up['basedir']).'serben-capas';if(!wp_mkdir_p($dir))return new WP_Error('mkdir','Não foi possível criar a pasta de capas.');
        $base=trailingslashit($up['baseurl']).'serben-capas';$cat=self::category($id);$name=get_the_title($id);$logo=self::logo_data($id);
        $lines=self::name_lines($name);$title_svg='';$title_y=count($lines)>1?610:630;
        foreach($lines as $i=>$line){$title_svg.='<text x="800" y="'.($title_y+$i*62).'" text-anchor="middle" font-family="Montserrat,Arial,sans-serif" font-size="54" font-weight="800" fill="#862F93">'.self::xml($line).'</text>';}
        $logo_svg=$logo?'<image href="'.self::xml($logo).'" x="540" y="235" width="520" height="285" preserveAspectRatio="xMidYMid meet"/>':'<text x="800" y="455" text-anchor="middle" font-family="Montserrat,Arial,sans-serif" font-size="170" font-weight="900" fill="#862F93">'.self::xml(self::upper(self::sub($name,0,1))).'</text>';
        $file='parceiro-'.$id.'-'.sanitize_title($cat['slug']).'.svg';$path=trailingslashit($dir).$file;$url=trailingslashit($base).$file;$bg=self::xml(self::BACKGROUND_URL);
        $svg='<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="1600" height="900" viewBox="0 0 1600 900"><image href="'.$bg.'" xlink:href="'.$bg.'" x="0" y="0" width="1600" height="900" preserveAspectRatio="xMidYMid slice"/>'.$logo_svg.$title_svg.'</svg>';
        if(false===file_put_contents($path,$svg,LOCK_EX))return new WP_Error('write','Não foi possível gravar a capa.');
        update_post_meta($id,self::META_URL,esc_url_raw($url));update_post_meta($id,self::META_PATH,$path);update_post_meta($id,self::META_CATEGORY,sanitize_title($cat['slug']));update_post_meta($id,self::META_VERSION,self::VERSION);return esc_url_raw($url);
    }
}
