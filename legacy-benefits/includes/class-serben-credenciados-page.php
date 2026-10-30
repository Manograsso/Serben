<?php
/**
 * Club Serben — Página de Ofertas V7
 * Versão: 9.0.0
 *
 * RECONSTRUÍDO DO ZERO.
 *
 * Conceito:
 * - Hero interno
 * - Busca principal
 * - Uma única navegação de filtro visível: categorias por ícones
 * - Ao selecionar categoria, aparecem somente suas subcategorias
 * - Sem sidebar
 * - Sem filtros duplicados
 * - Listing continua sendo o shortcode oficial do plugin
 *
 * Shortcode:
 * [serben_credenciados_page]
 */

defined('ABSPATH') || exit;

if (!class_exists('Serben_Credenciados_Page_V9', false)) {

final class Serben_Credenciados_Page_V9 {

    const VERSION = '3.0.0';

    private static $assets_loaded = false;

    public static function init() {
        add_action('wp_loaded', array(__CLASS__, 'register_shortcodes'), 30);
        // Garante que CSS/JS do marketplace já estejam disponíveis no wp_head,
        // inclusive quando o shortcode é renderizado pelo Elementor.
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'), 40);
    }

    public static function register_shortcodes() {
        add_shortcode('serben_credenciados_page', array(__CLASS__, 'shortcode_page'));
    }

    private static function hero() {
        if (shortcode_exists('serben_internal_hero')) {
            return do_shortcode('[serben_internal_hero title="Ofertas"]');
        }

        return '<section class="sov7-fallback-hero"><div class="sov7-container">'
            . '<nav><a href="' . esc_url(home_url('/')) . '">Início</a><span>/</span><strong>Ofertas</strong></nav>'
            . '<h1>Ofertas</h1>'
            . '</div></section>';
    }


    private static function provider() {
        $class = '\\SerbenConnect\\Providers\\PartnersProvider';
        return class_exists($class) ? new $class() : null;
    }

    private static function partner_array($partner) {
        if (!is_object($partner) || !method_exists($partner, 'toArray')) {
            return array();
        }

        $data = $partner->toArray();
        return is_array($data) ? $data : array();
    }

    private static function all_partners() {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        $provider = self::provider();

        if (!$provider || !method_exists($provider, 'all')) {
            return array();
        }

        $result = $provider->all(array(
            'limit'   => -1,
            'orderby' => 'title',
            'order'   => 'ASC',
        ));

        $items = isset($result['items']) && is_array($result['items'])
            ? $result['items']
            : array();

        if (!$items && post_type_exists('serben_parceiro') && method_exists($provider, 'find')) {
            $ids = get_posts(array(
                'post_type'      => 'serben_parceiro',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));

            foreach ((array) $ids as $id) {
                $partner = $provider->find((int) $id);
                if ($partner) {
                    $items[] = $partner;
                }
            }
        }

        $cache = array_values(array_filter($items, function($partner) {
            return is_object($partner) && method_exists($partner, 'id');
        }));

        return $cache;
    }

    private static function partner_name($partner) {
        if (method_exists($partner, 'name')) {
            $name = trim((string) $partner->name());
            if ($name !== '') return $name;
        }

        return get_the_title((int) $partner->id());
    }

    private static function normalize_type($partner) {
        $data = self::partner_array($partner);
        $id = (int) $partner->id();

        $type = remove_accents(strtolower(trim((string) (
            isset($data['tipo']) ? $data['tipo'] : get_post_meta($id, 'tipo', true)
        ))));

        if (
            strpos($type, 'online') !== false ||
            strpos($type, 'ecommerce') !== false ||
            strpos($type, 'e-commerce') !== false ||
            strpos($type, 'virtual') !== false ||
            strpos($type, 'digital') !== false
        ) {
            return 'online';
        }

        if (
            strpos($type, 'fisico') !== false ||
            strpos($type, 'presencial') !== false ||
            strpos($type, 'local') !== false
        ) {
            return 'presencial';
        }

        $advertiser = (int) get_post_meta($id, 'awin_advertiser_id', true);
        if ($advertiser > 0) return 'online';

        $affiliate = trim((string) get_post_meta($id, 'link_afiliado', true));
        $awin_url  = trim((string) get_post_meta($id, 'awin_click_through_url', true));

        if ($affiliate !== '' || $awin_url !== '') return 'online';

        return 'presencial';
    }

    private static function is_publicly_visible($partner) {
        $id = (int) $partner->id();
        $advertiser = (int) get_post_meta($id, 'awin_advertiser_id', true);
        $status = strtolower(trim((string) get_post_meta($id, 'awin_program_status', true)));

        return !($advertiser > 0 && $status !== '' && $status !== 'active');
    }

    private static function resolve_media_url($values, $size = 'medium') {
        foreach ((array) $values as $value) {
            if (is_array($value)) {
                $value = isset($value['url'])
                    ? $value['url']
                    : (isset($value['id']) ? $value['id'] : '');
            }

            if (is_numeric($value)) {
                $url = wp_get_attachment_image_url((int) $value, $size);
                if ($url) return esc_url_raw($url);
            }

            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                return esc_url_raw($value);
            }
        }

        return '';
    }

    private static function logo_url($partner) {
        $id = (int) $partner->id();
        if (class_exists('Serben_Utils')) {
            $url = Serben_Utils::partner_logo_url($id, 'large');
            if ($url !== '') return $url;
            if ('none' === sanitize_key((string) get_post_meta($id, SERBEN_META_PREFIX . 'logo_mode', true))) return '';
        }
        // Compatibilidade com objetos de integrações antigas somente quando não houve override manual.
        if (method_exists($partner, 'logoUrl')) {
            $logo = trim((string) $partner->logoUrl());
            if ($logo !== '') return esc_url_raw($logo);
        }
        return '';
    }


    private static function cover_url($partner) {
        $id = (int) $partner->id();

        // v1.11.0: a mesma resolução de capa é usada na vitrine e na página individual.
        if (class_exists('Serben_Cover_Studio')) {
            $approved = Serben_Cover_Studio::public_cover_url($id, 'large');
            if ($approved !== '') return esc_url_raw($approved);
        }

        return '';
    }


    /**
     * Cor de marca usada quando não existe imagem de capa.
     * Prioriza configuração/meta do parceiro e, em seguida, um pequeno mapa
     * de marcas recorrentes. O fallback é branco (#FFFFFF), conforme o sistema visual do parceiro.
     */
    private static function brand_color($partner) {
        $id = (int) $partner->id();
        return class_exists('Serben_Utils')
            ? Serben_Utils::brand_color($id, self::partner_name($partner))
            : '#FFFFFF';
    }

    private static function categories($partner) {
        $result = array();

        if (method_exists($partner, 'categories')) {
            foreach ((array) $partner->categories() as $item) {
                if (!is_array($item)) continue;

                $slug = sanitize_title((string) (
                    isset($item['slug']) ? $item['slug'] : ''
                ));

                $name = sanitize_text_field((string) (
                    isset($item['name']) ? $item['name'] :
                    (isset($item['nome']) ? $item['nome'] : $slug)
                ));

                if ($slug !== '') {
                    $result[$slug] = array(
                        'slug' => $slug,
                        'name' => $name !== '' ? $name : $slug,
                    );
                }
            }
        }

        $id = (int) $partner->id();
        $data = self::partner_array($partner);

        foreach (array(
            isset($data['categoria']) ? $data['categoria'] : '',
            get_post_meta($id, 'categoria', true),
        ) as $value) {
            $name = trim(wp_strip_all_tags((string) $value));
            if ($name === '') continue;

            $slug = sanitize_title($name);
            $result[$slug] = array('slug' => $slug, 'name' => $name);
        }

        return array_values($result);
    }

    private static function subcategories($partner) {
        $id = (int) $partner->id();
        $data = self::partner_array($partner);
        $result = array();

        foreach (array(
            isset($data['subcategoria']) ? $data['subcategoria'] : '',
            get_post_meta($id, 'subcategoria', true),
        ) as $value) {
            foreach (preg_split('/[,;|]+/u', (string) $value) as $part) {
                $part = trim(wp_strip_all_tags($part));
                if ($part !== '') {
                    $result[sanitize_title($part)] = $part;
                }
            }
        }

        return $result;
    }

    private static function category_aliases($slug) {
        $map = array(
            'moda'                     => array('vestuario-acessorios'),
            'casa-e-decoracao'         => array('casa-decoracao'),
            'beleza'                   => array('estetica-beleza', 'perfumarias'),
            'saude'                    => array('academias-esportes'),
            'eletronicos-e-tecnologia' => array('informatica', 'lojas-virtuais'),
            'viagem'                   => array('turismo-viagens'),
            'alimentos-e-bebidas'      => array('alimentacao', 'bebidas'),
            'esporte-e-lazer'          => array('academias-esportes', 'lazer-cultura'),
            'pets'                     => array('mundo-animal'),
            'varejo'                   => array('lojas-virtuais', 'lojas-de-departamentos', 'supermercado', 'supermercado-online'),
            'educacao'                 => array('cursos-educacao'),
            'infantil-e-brinquedos'    => array('artigos-infantis'),
        );

        return isset($map[$slug]) ? $map[$slug] : array();
    }

    private static function partner_search_text($partner) {
        $id = (int) $partner->id();
        $data = self::partner_array($partner);

        $parts = array(
            self::partner_name($partner),
            isset($data['descricao_curta']) ? $data['descricao_curta'] : '',
            isset($data['descricao_completa']) ? $data['descricao_completa'] : '',
            isset($data['cidade']) ? $data['cidade'] : '',
            isset($data['estado']) ? $data['estado'] : '',
            get_post_meta($id, 'descricao_curta', true),
            get_post_meta($id, 'desconto', true),
            get_post_meta($id, '_serben_desconto', true),
            get_post_meta($id, '_serben_desconto_online', true),
        );

        foreach (self::categories($partner) as $cat) {
            $parts[] = $cat['name'];
        }

        foreach (self::subcategories($partner) as $name) {
            $parts[] = $name;
        }

        return trim(wp_strip_all_tags(implode(' ', array_filter($parts))));
    }

    private static function benefit_label($partner) {
        $id = (int) $partner->id();
        $online = self::normalize_type($partner) === 'online';
        if (class_exists('Serben_Utils') && method_exists('Serben_Utils', 'benefit_text')) {
            return Serben_Utils::benefit_text($id, $online);
        }
        return $online ? 'Vantagem exclusiva na loja online' : 'Vantagem exclusiva para associados';
    }

    private static function render_partner_card($partner) {
        $id      = (int) $partner->id();
        $name    = self::partner_name($partner);
        $url     = get_permalink($id);
        $benefit = self::benefit_label($partner);
        $cover   = self::cover_url($partner);
        $logo    = self::logo_url($partner);
        $online  = self::normalize_type($partner) === 'online';
        $card_cover = (!$online && $cover !== '') ? $cover : '';
        $brand_profile = class_exists('Serben_Utils') ? Serben_Utils::brand_profile($id, $name) : array('color'=>'#2C2730','foreground'=>'#FFFFFF','source'=>'neutral','filter'=>'none');
        $brand   = (string) ($brand_profile['color'] ?? '#2C2730');
        $brand_fg = (string) ($brand_profile['foreground'] ?? '#FFFFFF');
        $brand_source = sanitize_html_class((string) ($brand_profile['source'] ?? 'neutral'));
        $cats    = self::categories($partner);
        $subs    = self::subcategories($partner);

        $category_values = array();
        foreach ($cats as $cat) {
            $category_values[] = (string) ($cat['name'] ?? '');
            $category_values[] = (string) ($cat['slug'] ?? '');
        }
        foreach ($subs as $sub_slug => $sub_name) {
            $category_values[] = (string) $sub_slug;
            $category_values[] = (string) $sub_name;
        }

        $tokens = class_exists('Serben_Utils') ? Serben_Utils::market_filter_tokens($category_values) : array();
        foreach ($cats as $cat) if (!empty($cat['slug'])) $tokens[] = (string) $cat['slug'];
        foreach (array_keys($subs) as $sub_slug) $tokens[] = (string) $sub_slug;
        $tokens = array_values(array_unique(array_filter($tokens)));

        $initials = '';
        foreach (preg_split('/\s+/u', trim($name)) as $word) {
            if ($word === '') continue;
            $initials .= function_exists('mb_substr') ? mb_substr($word, 0, 1, 'UTF-8') : substr($word, 0, 1);
            if ((function_exists('mb_strlen') ? mb_strlen($initials, 'UTF-8') : strlen($initials)) >= 2) break;
        }
        $initials = function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);

        $visual_style = '--brand-bg:' . $brand . ';--brand-fg:' . $brand_fg . ';';
        if ($card_cover !== '') {
            $visual_style .= '--media-image:url(\'' . esc_url($card_cover) . '\');';
        }

        ob_start();
        ?>
        <article
            class="serben-market-card<?php echo $card_cover !== '' ? ' has-cover' : ' has-logo'; ?>"
            data-serben-market-card
            data-type="<?php echo esc_attr(self::normalize_type($partner) === 'online' ? 'online' : 'fisico'); ?>"
            data-search="<?php echo esc_attr(remove_accents(strtolower(self::partner_search_text($partner)))); ?>"
            data-categories="<?php echo esc_attr('|' . implode('|', $tokens) . '|'); ?>"
        >
            <a class="serben-market-card__surface" href="<?php echo esc_url($url); ?>" aria-label="<?php echo esc_attr('Ver oferta de ' . $name); ?>">
                <span class="serben-market-card__visual<?php echo $card_cover !== '' ? ' is-cover' : ' is-logo is-brand-' . esc_attr($brand_source); ?>" style="<?php echo esc_attr($visual_style); ?>">
                    <?php if ($card_cover !== '') : ?>
                        <img class="serben-market-card__cover" src="<?php echo esc_url($card_cover); ?>" alt="<?php echo esc_attr('Imagem de ' . $name); ?>" loading="lazy" decoding="async">
                    <?php elseif ($logo !== '') : ?>
                        <img class="serben-market-card__brand-logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr('Logo ' . $name); ?>" loading="lazy" decoding="async">
                    <?php else : ?>
                        <span class="serben-market-card__initials"><?php echo esc_html($initials ?: 'CS'); ?></span>
                    <?php endif; ?>
                </span>
                <span class="serben-market-card__body">
                    <span class="serben-market-card__brandline">
                        <?php if ($card_cover !== '' && $logo !== '') : ?><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr('Logo ' . $name); ?>" loading="lazy" decoding="async"><?php endif; ?>
                        <strong class="serben-market-card__name"><?php echo esc_html($name); ?></strong>
                    </span>
                    <span class="serben-market-card__benefit"><?php echo esc_html(wp_trim_words($benefit, 13, '…')); ?></span>
                    <span class="serben-market-card__cta">Ver oferta <?php echo Serben_Utils::fa_icon('arrow-right', 'serben-market-card__cta-icon'); ?></span>
                </span>
            </a>
        </article>
        <?php
        return ob_get_clean();
    }

    private static function listing() {
        $visible = array();
        foreach (self::all_partners() as $partner) {
            if (self::is_publicly_visible($partner)) $visible[] = $partner;
        }
        usort($visible, function($a, $b) {
            return strnatcasecmp(self::partner_name($a), self::partner_name($b));
        });

        ob_start();
        ?>
        <div class="serben-market-listing" data-serben-market-listing>
            <div class="serben-market-count"><strong data-serben-market-count><?php echo esc_html(count($visible)); ?></strong><span>benefícios encontrados</span></div>
            <div class="serben-market-grid">
                <?php foreach ($visible as $partner) echo self::render_partner_card($partner); ?>
            </div>
            <div class="serben-market-empty" data-serben-market-empty hidden>
                <?php echo Serben_Utils::fa_icon('search', 'serben-market-empty__icon'); ?>
                <strong>Nenhuma oferta encontrada</strong>
                <span>Tente outro termo ou escolha outra categoria.</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

private static function category_catalogue() {
        return class_exists('Serben_Utils') ? Serben_Utils::market_categories() : array();
    }

    /**
     * Garante a árvore real da taxonomia, inclusive categorias vazias.
     */
    public static function seed_category_taxonomy() {
        if (!defined('SERBEN_TAXONOMY') || !taxonomy_exists(SERBEN_TAXONOMY)) {
            return;
        }

        foreach (self::category_catalogue() as $group) {
            $parent_name = trim((string) $group['name']);
            if ($parent_name === '') continue;

            $parent = term_exists($parent_name, SERBEN_TAXONOMY);

            if (!$parent) {
                $parent = wp_insert_term(
                    $parent_name,
                    SERBEN_TAXONOMY,
                    array('slug' => sanitize_title($parent_name))
                );
            }

            if (is_wp_error($parent)) continue;

            $parent_id = is_array($parent) ? absint($parent['term_id']) : absint($parent);
            if (!$parent_id) continue;

            foreach ((array) $group['children'] as $child) {
                $child_name = is_array($child) ? trim((string) ($child['name'] ?? '')) : trim((string) $child);
                $child_slug = is_array($child) ? trim((string) ($child['slug'] ?? '')) : sanitize_title($child_name);
                if ($child_name === '') continue;

                if (!term_exists($child_name, SERBEN_TAXONOMY, $parent_id)) {
                    wp_insert_term(
                        $child_name,
                        SERBEN_TAXONOMY,
                        array(
                            'slug'   => $child_slug !== '' ? $child_slug : sanitize_title($parent_name . '-' . $child_name),
                            'parent' => $parent_id,
                        )
                    );
                }
            }
        }
    }

    private static function term_slug($name, $parent_name = '') {
        if (!defined('SERBEN_TAXONOMY') || !taxonomy_exists(SERBEN_TAXONOMY)) {
            return sanitize_title($parent_name ? $parent_name . '-' . $name : $name);
        }

        if ($parent_name !== '') {
            $parent = term_exists($parent_name, SERBEN_TAXONOMY);
            $parent_id = is_array($parent) ? absint($parent['term_id']) : absint($parent);

            if ($parent_id) {
                $term = term_exists($name, SERBEN_TAXONOMY, $parent_id);
                if (is_array($term) && !empty($term['term_id'])) {
                    $obj = get_term(absint($term['term_id']), SERBEN_TAXONOMY);
                    if ($obj && !is_wp_error($obj)) return $obj->slug;
                }
            }
        } else {
            $term = term_exists($name, SERBEN_TAXONOMY);
            if (is_array($term) && !empty($term['term_id'])) {
                $obj = get_term(absint($term['term_id']), SERBEN_TAXONOMY);
                if ($obj && !is_wp_error($obj)) return $obj->slug;
            }
        }

        return sanitize_title($parent_name ? $parent_name . '-' . $name : $name);
    }

    private static function icon_for_category($name) {
        foreach (self::category_catalogue() as $group) {
            if ((string) ($group['name'] ?? '') === (string) $name) return (string) ($group['icon'] ?? 'tag');
        }
        return 'tag';
    }


    private static function discovery() {
        $catalogue = self::category_catalogue();

        ob_start();
        ?>
        <section class="serben-market-discovery">
            <div class="serben-market-container">
                <div class="serben-market-search">
                    <?php echo Serben_Utils::fa_icon('search', 'serben-market-search__icon'); ?>
                    <input type="search" data-serben-market-search placeholder="Busque por parceiro, benefício ou categoria" autocomplete="off">
                    <button type="button" data-serben-market-clear hidden aria-label="Limpar busca"><?php echo Serben_Utils::fa_icon('close', 'serben-market-search__close'); ?></button>
                </div>

                <div class="serben-market-category-caption">
                    <strong>Categorias</strong>
                    <span>Escolha uma categoria principal. As subcategorias aparecem depois da seleção.</span>
                </div>

                <div class="serben-market-category-wrap">
                    <button type="button" class="serben-market-category serben-market-category--fixed is-active" data-serben-market-all data-serben-market-slug="">
                        <span><?php echo Serben_Utils::fa_icon('grid'); ?></span>
                        <strong>Todos</strong>
                    </button>

                    <div class="serben-market-category-scroll">
                        <button type="button" class="serben-market-category-nav is-prev" data-serben-market-cat-prev aria-label="Categorias anteriores"><?php echo Serben_Utils::fa_icon('chevron-left'); ?></button>
                        <div class="serben-market-categories" data-serben-market-categories>
                            <?php foreach ($catalogue as $index => $group) : $slug = (string) ($group['slug'] ?? sanitize_title($group['name'])); ?>
                                <button type="button" class="serben-market-category" data-serben-market-parent="<?php echo esc_attr($index); ?>" data-serben-market-slug="<?php echo esc_attr($slug); ?>" data-serben-market-name="<?php echo esc_attr($group['name']); ?>">
                                    <span><?php echo Serben_Utils::fa_icon((string) ($group['icon'] ?? self::icon_for_category($group['name']))); ?></span>
                                    <strong><?php echo esc_html($group['name']); ?></strong>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="serben-market-category-nav is-next" data-serben-market-cat-next aria-label="Próximas categorias"><?php echo Serben_Utils::fa_icon('chevron-right'); ?></button>
                    </div>
                </div>

                <div class="serben-market-subcategories" data-serben-market-subcategories hidden>
                    <div class="serben-market-subcategories__head">
                        <div><small>Refine por subcategoria</small><strong data-serben-market-subtitle></strong></div>
                        <button type="button" data-serben-market-close-sub aria-label="Fechar subcategorias"><?php echo Serben_Utils::fa_icon('close'); ?></button>
                    </div>
                    <?php foreach ($catalogue as $index => $group) : $group_slug = (string) ($group['slug'] ?? sanitize_title($group['name'])); ?>
                        <div class="serben-market-subpanel" data-serben-market-panel="<?php echo esc_attr($index); ?>" hidden>
                            <button type="button" class="serben-market-chip is-all" data-serben-market-filter="<?php echo esc_attr($group_slug); ?>">Todas em <?php echo esc_html($group['name']); ?></button>
                            <?php foreach ((array) ($group['children'] ?? array()) as $child) :
                                $child_name = is_array($child) ? (string) ($child['name'] ?? '') : (string) $child;
                                $child_slug = is_array($child) ? (string) ($child['slug'] ?? sanitize_title($child_name)) : sanitize_title($child_name);
                                if ($child_name === '') continue;
                            ?>
                                <button type="button" class="serben-market-chip" data-serben-market-filter="<?php echo esc_attr($group_slug . '--' . $child_slug); ?>"><?php echo esc_html($child_name); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="serben-market-active" data-serben-market-active hidden>
                    <span>Exibindo:</span><strong data-serben-market-active-label></strong>
                    <button type="button" data-serben-market-reset aria-label="Remover filtro"><?php echo Serben_Utils::fa_icon('close'); ?></button>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }


    public static function shortcode_page($atts = array()) {
        self::enqueue_assets();
        wp_enqueue_style('serben-public');
        wp_enqueue_script('serben-public');

        ob_start();
        ?>
        <main class="serben-market">
            <section class="serben-market-header">
                <div class="serben-market-container">
                    <nav class="serben-market-breadcrumb" aria-label="Navegação estrutural"><a href="<?php echo esc_url(home_url('/')); ?>">Início</a><span>›</span><strong>Ofertas</strong></nav>
                    <span class="serben-market-eyebrow">CLUB SERBEN</span>
                    <h1>Ofertas e benefícios</h1>
                    <p>Descubra vantagens exclusivas, parceiros online e benefícios presenciais em uma experiência simples e organizada.</p>
                </div>
            </section>

            <?php echo self::discovery(); ?>

            <section class="serben-market-results">
                <div class="serben-market-container">
                    <div class="serben-market-results__head">
                        <div><span>SELEÇÃO CLUB SERBEN</span><h2>Benefícios para <strong>aproveitar agora</strong></h2></div>
                    </div>
                    <?php echo self::listing(); ?>
                </div>
            </section>
        </main>
        <?php
        return ob_get_clean();
    }

    public static function enqueue_assets() {
        if (self::$assets_loaded) return;
        self::$assets_loaded = true;

        wp_enqueue_style('serben-inter-font');
        wp_register_style('serben-market-v2', false, array('serben-inter-font'), SERBEN_PLUGIN_VERSION);
        wp_enqueue_style('serben-market-v2');
        wp_add_inline_style('serben-market-v2', self::css());

        wp_register_script('serben-market-v2', false, array('jquery'), SERBEN_PLUGIN_VERSION, true);
        wp_enqueue_script('serben-market-v2');
        wp_add_inline_script('serben-market-v2', self::js());
    }

    private static function css() {
        return <<<'CSS'
.serben-market,.serben-market *{box-sizing:border-box}
.serben-market{--purple:#862F93;--purple-dark:#651F70;--orange:#F6A427;--ink:#241D27;--muted:#706775;--line:#E9E2EC;--soft:#FAF8FB;--soft-purple:#F5F0F8;--max:1360px;background:#fff;color:var(--ink);font-family:"Montserrat",Arial,sans-serif}
.serben-market i{font-style:normal}
.serben-market-container{width:min(var(--max),calc(100% - 48px));margin:0 auto}
.serben-market-header{padding:34px 0 28px;border-bottom:1px solid #F1ECF3;background:#fff}
.serben-market-breadcrumb{display:flex;gap:8px;align-items:center;margin-bottom:18px;color:#918694;font-size:11px;font-weight:600}.serben-market-breadcrumb a{color:var(--purple);font-weight:700;text-decoration:none}.serben-market-breadcrumb strong{color:#4B424E}
.serben-market-eyebrow,.serben-market-section-head>div>span,.serben-market-results__head>div>span{display:block;margin-bottom:7px;color:var(--orange);font-size:10px;font-weight:800;letter-spacing:.8px;text-transform:uppercase}
.serben-market-header h1{margin:0;color:var(--ink);font-size:clamp(34px,4vw,50px);line-height:1.02;letter-spacing:-1.5px;font-weight:700}.serben-market-header>div>p{max-width:760px;margin:11px 0 0;color:var(--muted);font-size:14px;line-height:1.65}
.serben-market-discovery{padding:28px 0 30px;border-bottom:1px solid #F1ECF3;background:#fff}
.serben-market-search{height:56px;display:flex;align-items:center;gap:12px;padding:0 12px 0 18px;border:1px solid #DDD4E2;border-radius:14px;background:#fff;transition:border-color .18s ease,box-shadow .18s ease}.serben-market-search:focus-within{border-color:var(--purple);box-shadow:0 0 0 4px rgba(113,55,163,.06)}
.serben-market-search__icon{width:18px;color:var(--purple);font-size:16px}.serben-market-search input{flex:1 1 auto;min-width:0;height:100%;padding:0;border:0!important;outline:0!important;background:transparent!important;color:var(--ink)!important;box-shadow:none!important;font:500 14px/1 "Montserrat",Arial,sans-serif!important;text-transform:none!important}.serben-market-search input::placeholder{color:#9A909E}
.serben-market-search button{width:34px!important;height:34px!important;display:grid!important;place-items:center!important;padding:0!important;border:0!important;border-radius:50%!important;background:var(--soft-purple)!important;color:var(--purple)!important;cursor:pointer!important;box-shadow:none!important}.serben-market-search button:hover{background:#EBE0F1!important}.serben-market-search__close{font-size:13px}
.serben-market-section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:28px;margin:28px 0 18px}.serben-market-section-head h2,.serben-market-results__head h2{margin:0;color:var(--ink);font-size:clamp(23px,2.2vw,30px);line-height:1.13;letter-spacing:-.65px;font-weight:500}.serben-market-section-head h2 strong,.serben-market-results__head h2 strong{color:var(--purple);font-weight:750}.serben-market-section-head p{max-width:440px;margin:0 0 2px;color:#877D8B;font-size:11.5px;line-height:1.55;text-align:right}

/* Categoria fixa + trilho navegável. O fundo externo nunca recebe cor. */
.serben-market-category-wrap{display:grid;grid-template-columns:120px minmax(0,1fr);gap:14px;align-items:start}
.serben-market-category-scroll{position:relative;min-width:0;display:grid;grid-template-columns:40px minmax(0,1fr) 40px;gap:9px;align-items:center}
.serben-market-categories{display:flex;gap:10px;min-width:0;overflow-x:auto;padding:2px 1px 8px;scrollbar-width:none;scroll-behavior:smooth;overscroll-behavior-inline:contain;scroll-snap-type:x mandatory}.serben-market-categories::-webkit-scrollbar{display:none}
.serben-market-category,.serben-market-category--fixed{appearance:none!important;flex:0 0 116px!important;width:116px!important;height:124px!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:flex-start!important;gap:10px!important;margin:0!important;padding:4px 2px 0!important;border:0!important;border-radius:0!important;background:transparent!important;background-color:transparent!important;color:#201A23!important;box-shadow:none!important;cursor:pointer!important;font-family:inherit!important;text-align:center!important;scroll-snap-align:start!important;transition:color .18s ease,transform .18s ease!important}
.serben-market-category--fixed{justify-self:start!important}
.serben-market-category:hover{transform:translateY(-2px)!important;color:var(--purple)!important;background:transparent!important}
.serben-market-category>span{width:76px;height:76px;display:grid;place-items:center;flex:0 0 76px;border:1px solid #DED5E3;border-radius:50%;background:#fff;color:var(--purple);font-size:24px;transition:border-color .18s ease,background .18s ease,color .18s ease,box-shadow .18s ease,transform .18s ease}
.serben-market-category:hover>span{border-color:#CBB8D6;box-shadow:0 9px 24px rgba(71,42,84,.08)}
.serben-market-category.is-active>span{border-color:var(--purple);background:var(--purple);color:#fff;box-shadow:0 8px 20px rgba(113,55,163,.16)}
.serben-market-category strong{display:flex;align-items:flex-start;justify-content:center;width:100%;min-height:34px;color:inherit;font-size:13px;line-height:1.2;font-weight:650;white-space:normal}
.serben-market-category-nav{appearance:none!important;width:40px!important;height:40px!important;display:grid!important;place-items:center!important;margin:0!important;padding:0!important;border:1px solid var(--line)!important;border-radius:50%!important;background:#fff!important;color:var(--purple)!important;box-shadow:none!important;cursor:pointer!important;position:relative;z-index:4;transition:background .18s ease,color .18s ease,border-color .18s ease,transform .18s ease!important}
.serben-market-category-nav:hover:not([disabled]){border-color:var(--purple)!important;background:var(--purple)!important;color:#fff!important;transform:translateY(-1px)}.serben-market-category-nav[disabled]{opacity:.25!important;cursor:not-allowed!important;transform:none!important}.serben-market-category-nav i{font-size:13px}.serben-market-category-nav.is-prev i{transform:none}
.serben-market-subcategories{margin-top:14px;padding:18px;border:1px solid var(--line);border-radius:16px;background:#FCFAFD}.serben-market-subcategories__head{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:14px}.serben-market-subcategories__head small{display:block;margin-bottom:4px;color:#958A99;font-size:9px;font-weight:800;letter-spacing:.7px;text-transform:uppercase}.serben-market-subcategories__head strong{color:var(--ink);font-size:14px;font-weight:750}
.serben-market-subcategories__head button,.serben-market-active button{appearance:none!important;width:32px!important;height:32px!important;display:grid!important;place-items:center!important;padding:0!important;border:0!important;border-radius:50%!important;background:#EEE5F3!important;color:var(--purple)!important;box-shadow:none!important;cursor:pointer!important}.serben-market-subcategories__head button:hover,.serben-market-active button:hover{background:var(--purple)!important;color:#fff!important}
.serben-market-subpanel{display:flex;gap:8px;flex-wrap:wrap}.serben-market-chip{appearance:none!important;min-height:36px!important;padding:0 14px!important;border:1px solid #DDD2E3!important;border-radius:999px!important;background:#fff!important;color:#5E5362!important;box-shadow:none!important;font:700 10.5px/1 "Montserrat",Arial,sans-serif!important;cursor:pointer!important;transition:.16s ease!important}.serben-market-chip:hover,.serben-market-chip.is-active{border-color:var(--purple)!important;background:var(--purple)!important;color:#fff!important}.serben-market-chip[hidden]{display:none!important}
.serben-market-active{display:flex;align-items:center;gap:8px;margin-top:14px;color:#817585;font-size:11px}.serben-market-active strong{color:var(--purple);font-weight:800}

.serben-market-results{padding:36px 0 82px;background:#fff}.serben-market-results__head{margin-bottom:20px}.serben-market-count{display:flex;align-items:baseline;justify-content:flex-end;gap:6px;margin:-40px 0 18px;color:#857987;font-size:11px}.serben-market-count strong{color:var(--purple);font-size:18px;font-weight:800}
.serben-market-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:16px;align-items:stretch}.serben-market-card{position:relative;min-width:0;transition:transform .2s ease}.serben-market-card:hover{transform:translateY(-4px)}
.serben-market-card__surface{display:flex;height:100%;min-height:268px;flex-direction:column;overflow:hidden;border:1px solid var(--line);border-radius:16px;background:#fff;color:var(--ink)!important;text-decoration:none!important;box-shadow:0 7px 22px rgba(46,29,55,.045);transition:border-color .2s ease,box-shadow .2s ease}.serben-market-card:hover .serben-market-card__surface{border-color:#D3C3DC;box-shadow:0 16px 34px rgba(48,30,57,.10)}
.serben-market-card__visual{position:relative;isolation:isolate;width:100%;height:156px;display:grid;place-items:center;overflow:hidden;background:#F7F4F8}
.serben-market-card__visual.is-cover{background:color-mix(in srgb,var(--brand-bg,#F5F2F6) 8%,#fff)}.serben-market-card__visual.is-cover:before{content:"";position:absolute;inset:-12px;z-index:-2;background-image:var(--media-image);background-size:cover;background-position:center;filter:blur(14px);opacity:.20;transform:scale(1.08)}.serben-market-card__visual.is-cover:after{content:"";position:absolute;inset:0;z-index:-1;background:rgba(255,255,255,.68)}
.serben-market-card__cover{position:relative;z-index:1;display:block;width:100%;height:100%;object-fit:contain;object-position:center;padding:0;transition:transform .22s ease}.serben-market-card:hover .serben-market-card__cover{transform:scale(1.018)}
.serben-market-card__visual.is-logo{padding:18px;background:var(--brand-bg,#2C2730)}.serben-market-card__brand-logo{display:block;width:auto;height:auto;max-width:78%;max-height:62%;object-fit:contain;object-position:center;transition:transform .18s ease}.serben-market-card__visual.is-brand-auto .serben-market-card__brand-logo,.serben-market-card__visual.is-brand-neutral .serben-market-card__brand-logo{mix-blend-mode:multiply}.serben-market-card:hover .serben-market-card__brand-logo{transform:scale(1.025)}
.serben-market-card__logo-badge{position:absolute;left:10px;bottom:10px;z-index:4;width:58px;height:42px;display:grid;place-items:center;padding:6px;border:1px solid rgba(255,255,255,.86);border-radius:9px;background:var(--brand-bg,#fff);box-shadow:0 6px 16px rgba(29,18,35,.14);overflow:hidden}.serben-market-card__logo-badge img{display:block;width:auto;height:auto;max-width:92%;max-height:88%;object-fit:contain}.serben-market-card__logo-badge.is-brand-auto img,.serben-market-card__logo-badge.is-brand-neutral img{mix-blend-mode:multiply}.serben-market-card__logo-badge b{color:var(--brand-fg,#fff);font-size:13px;font-weight:800}
.serben-market-card__initials{display:grid;place-items:center;width:74px;height:74px;border-radius:50%;border:1px solid color-mix(in srgb,var(--brand-fg,#fff) 42%,transparent);color:var(--brand-fg,#fff);font-size:26px;font-weight:800}
.serben-market-card__body{flex:1 1 auto;display:flex;flex-direction:column;align-items:flex-start;padding:13px 14px 14px}.serben-market-card__name{display:block;margin:0;color:var(--ink)!important;font-size:14px;line-height:1.25;font-weight:700;letter-spacing:-.12px}.serben-market-card__benefit{display:block;margin-top:5px;color:#655B69;font-size:12px;line-height:1.4;font-weight:500}.serben-market-card__cta{display:inline-flex;align-items:center;gap:7px;margin-top:auto;padding-top:11px;color:var(--purple);font-size:10.5px;line-height:1;font-weight:800;text-transform:uppercase;letter-spacing:.2px}.serben-market-card__cta-icon{font-size:10px;transition:transform .18s ease}.serben-market-card:hover .serben-market-card__cta-icon{transform:translateX(3px)}
.serben-market-empty{display:grid;place-items:center;gap:8px;padding:58px 20px;text-align:center;color:#7D7281}.serben-market-empty__icon{font-size:24px;color:var(--purple)}.serben-market-empty strong{color:var(--ink);font-size:18px}.serben-market-empty span{font-size:12px}.serben-market [hidden]{display:none!important}
@media(max-width:1240px){.serben-market-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:1020px){.serben-market-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.serben-market-section-head{align-items:flex-start;flex-direction:column;gap:8px}.serben-market-section-head p{text-align:left}.serben-market-count{justify-content:flex-start;margin:0 0 18px}.serben-market-category-wrap{grid-template-columns:112px minmax(0,1fr)}.serben-market-category,.serben-market-category--fixed{flex-basis:108px!important;width:108px!important}}
@media(max-width:780px){.serben-market-container{width:calc(100% - 30px)}.serben-market-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.serben-market-header{padding-top:25px}.serben-market-category-wrap{grid-template-columns:98px minmax(0,1fr);gap:10px}.serben-market-category-scroll{grid-template-columns:minmax(0,1fr)}.serben-market-category-nav{display:none!important}.serben-market-category,.serben-market-category--fixed{flex-basis:94px!important;width:94px!important;height:114px!important}.serben-market-category>span{width:68px;height:68px;flex-basis:68px;font-size:21px}.serben-market-category strong{font-size:12px}.serben-market-card__visual{height:148px}}
@media(max-width:520px){.serben-market-header h1{font-size:34px}.serben-market-section-head p{font-size:11px}.serben-market-search{height:54px}.serben-market-subcategories{padding:15px}.serben-market-card__surface{min-height:250px}.serben-market-card__visual{height:138px}.serben-market-card__body{padding:12px}.serben-market-card__name{font-size:13.5px}.serben-market-card__benefit{font-size:11.5px}}
@media(max-width:390px){.serben-market-grid{grid-template-columns:1fr}.serben-market-card__surface{min-height:258px}.serben-market-card__visual{height:154px}}

/* Club Serben v2.5.0 — sistema visual 16px + cor manual */
.serben-market,.serben-market *{font-family:"Montserrat",Arial,sans-serif!important}
.serben-market{font-size:16px!important}
.serben-market-breadcrumb,.serben-market-header>div>p,.serben-market-search input,.serben-market-category strong,.serben-market-subcategories__head strong,.serben-market-chip,.serben-market-active,.serben-market-count,.serben-market-empty span{font-size:16px!important}
.serben-market-eyebrow,.serben-market-section-head>div>span,.serben-market-results__head>div>span,.serben-market-subcategories__head small{font-size:16px!important}
.serben-market-header h1{font-size:clamp(36px,4vw,52px)!important;line-height:1.08!important}.serben-market-section-head h2,.serben-market-results__head h2{font-size:32px!important}
.serben-market-category{flex:0 0 142px!important;min-height:132px!important;gap:10px!important;padding:10px 8px!important}.serben-market-category>span{width:76px!important;height:76px!important;font-size:24px!important}.serben-market-category strong{min-height:40px!important;line-height:1.22!important;font-weight:700!important}
.serben-market-card__surface{min-height:330px!important;border-radius:18px!important}.serben-market-card__visual{height:190px!important;min-height:190px!important;padding:0!important;background:var(--brand-bg,#FFFFFF)!important;overflow:hidden!important}
.serben-market-card__visual.is-cover:before,.serben-market-card__visual.is-cover:after{display:none!important}.serben-market-card__cover{width:100%!important;height:100%!important;object-fit:cover!important;object-position:center!important;padding:0!important;transform:none!important}.serben-market-card:hover .serben-market-card__cover{transform:scale(1.02)!important}
.serben-market-card__visual.is-logo{padding:24px!important;background:var(--brand-bg,#FFFFFF)!important}.serben-market-card__brand-logo{max-width:72%!important;max-height:64%!important;filter:none!important;mix-blend-mode:normal!important;transform:none!important}.serben-market-card:hover .serben-market-card__brand-logo{transform:scale(1.02)!important}
.serben-market-card__logo-badge{display:none!important}.serben-market-card__body{padding:16px 18px 18px!important;gap:0!important}.serben-market-card__brandline{display:flex!important;align-items:center!important;gap:10px!important;min-width:0!important}.serben-market-card__brandline>img{flex:0 0 auto!important;width:auto!important;height:auto!important;max-width:54px!important;max-height:28px!important;object-fit:contain!important;filter:none!important;mix-blend-mode:normal!important}.serben-market-card__name{font-size:20px!important;line-height:1.25!important;font-weight:700!important}.serben-market-card__benefit{margin-top:8px!important;font-size:16px!important;line-height:1.5!important;font-weight:500!important}.serben-market-card__cta{padding-top:14px!important;font-size:16px!important;line-height:1.2!important;font-weight:700!important;text-transform:none!important}.serben-market-card__cta-icon{font-size:14px!important}
.serben-market-card:hover{transform:translateY(-3px)!important}.serben-market-card:hover .serben-market-card__surface{border-color:#CDBDDD!important;box-shadow:0 14px 30px rgba(55,34,66,.10)!important}
@media(max-width:760px){.serben-market-category{flex-basis:126px!important;min-height:122px!important}.serben-market-category>span{width:68px!important;height:68px!important}.serben-market-card__visual{height:176px!important;min-height:176px!important}.serben-market-card__surface{min-height:316px!important}}


/* =========================================================
   Club Serben v2.8.0 — refinamento de marketplace
   ========================================================= */
.serben-market{--purple:#862F93;--purple-dark:#651F70;--orange:#F6A427;--soft-purple:#F8F1FA;--max:1360px}
.serben-market-grid{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:20px!important}
.serben-market-card{transition:transform .24s cubic-bezier(.2,.7,.2,1)!important}
.serben-market-card:hover{transform:translateY(-5px)!important}
.serben-market-card__surface{border-radius:18px!important;box-shadow:0 8px 24px rgba(44,29,52,.05)!important;transition:border-color .22s ease,box-shadow .22s ease,transform .22s ease!important}
.serben-market-card:hover .serben-market-card__surface{border-color:rgba(134,47,147,.34)!important;box-shadow:0 18px 38px rgba(52,30,62,.12)!important}
.serben-market-card__visual{overflow:hidden!important}
.serben-market-card__cover,.serben-market-card__brand-logo{transition:transform .4s cubic-bezier(.2,.7,.2,1)!important}
.serben-market-card:hover .serben-market-card__cover{transform:scale(1.025)!important}
.serben-market-card:hover .serben-market-card__brand-logo{transform:scale(1.035)!important}
.serben-market-card__cta{color:#862F93!important}
.serben-market-card:hover .serben-market-card__cta{color:#651F70!important}
.serben-market-category:hover>span{border-color:rgba(134,47,147,.42)!important;box-shadow:0 10px 26px rgba(72,40,83,.09)!important;transform:translateY(-2px)}
.serben-market-category.is-active>span{border-color:#862F93!important;background:#862F93!important;color:#fff!important;box-shadow:0 9px 22px rgba(134,47,147,.18)!important}
.serben-market-category-nav:hover:not([disabled]){border-color:#862F93!important;background:#862F93!important;color:#fff!important}
@media(max-width:1180px){.serben-market-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}
@media(max-width:780px){.serben-market-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:14px!important}}
@media(max-width:430px){.serben-market-grid{grid-template-columns:1fr!important}}


/* v2.8.0 — tipografia pública a partir de 16px */
.serben-market-breadcrumb,
.serben-market-header>div>p,
.serben-market-search input,
.serben-market-category strong,
.serben-market-section-head p,
.serben-market-subcategories__head strong,
.serben-market-chip,
.serben-market-active,
.serben-market-count,
.serben-market-card__benefit,
.serben-market-card__cta{font-size:16px!important}
.serben-market-eyebrow,.serben-market-section-head>div>span,.serben-market-results__head>div>span,.serben-market-subcategories__head small{font-size:16px!important;letter-spacing:.25px!important}
.serben-market-count strong{font-size:20px!important}
.serben-market-category strong{line-height:1.25!important;font-weight:600!important}


/* =========================================================
   Club Serben v2.9.0 — marketplace objetivo + marca
   ========================================================= */
.serben-market-category-caption{display:flex;align-items:center;justify-content:space-between;gap:24px;margin:28px 0 18px;padding:0 2px}
.serben-market-category-caption strong{position:relative;color:var(--ink);font-size:20px!important;line-height:1.25;font-weight:700!important}
.serben-market-category-caption strong:after{content:"";display:block;width:38px;height:3px;margin-top:7px;border-radius:99px;background:var(--orange)}
.serben-market-category-caption span{max-width:620px;color:var(--muted);font-size:16px!important;line-height:1.55;text-align:right}
.serben-market-search{border-color:#E3DAE7!important;box-shadow:0 5px 18px rgba(46,31,54,.025)!important}
.serben-market-search:focus-within{border-color:var(--orange)!important;box-shadow:0 0 0 4px rgba(246,164,39,.12)!important}
.serben-market-search button:hover{background:#FFF4DE!important;color:var(--purple)!important}
.serben-market-category:hover>span{border-color:var(--orange)!important;background:#FFF9EE!important;color:var(--purple)!important;box-shadow:0 10px 26px rgba(246,164,39,.14)!important}
.serben-market-category.is-active>span{border-color:var(--purple)!important;background:var(--purple)!important;color:#fff!important;box-shadow:0 10px 26px rgba(134,47,147,.18)!important}
.serben-market-category.is-active strong{color:var(--purple)!important}
.serben-market-category-nav:hover:not([disabled]){border-color:var(--orange)!important;background:var(--orange)!important;color:#271A09!important;box-shadow:0 8px 20px rgba(246,164,39,.18)!important}
.serben-market-chip:hover{border-color:var(--orange)!important;background:#FFF7E7!important;color:var(--purple)!important}
.serben-market-chip.is-active{border-color:var(--purple)!important;background:var(--purple)!important;color:#fff!important}
.serben-market-card:hover .serben-market-card__surface{border-color:rgba(246,164,39,.70)!important;box-shadow:0 18px 38px rgba(52,30,62,.11)!important}
.serben-market-card__cta{color:var(--purple)!important}
.serben-market-card__cta-icon{color:var(--orange)!important}
.serben-market-card:hover .serben-market-card__cta{color:var(--purple-dark)!important}
.serben-market-card:hover .serben-market-card__cta-icon{color:var(--orange)!important;transform:translateX(4px) scale(1.05)!important}
.serben-market-active strong{color:var(--purple)!important}
.serben-market-results{padding-top:30px!important}
@media(max-width:820px){.serben-market-category-caption{align-items:flex-start;flex-direction:column;gap:8px}.serben-market-category-caption span{text-align:left}.serben-market-category-caption strong{font-size:18px!important}}
.serben-market :is(button,a,input):focus-visible{outline:3px solid rgba(246,164,39,.55)!important;outline-offset:3px!important}

CSS;
    }


    private static function js() {
        return <<<'JS'
(function($){
    'use strict';
    var state={search:'',category:'',type:''},timer=null;
    function page(){return $('.serben-market').first();}
    function normalize(value){return String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();}
    function activeLabel(label){var $box=page().find('[data-serben-market-active]');if(!label){$box.prop('hidden',true);$box.find('[data-serben-market-active-label]').text('');return;}$box.find('[data-serben-market-active-label]').text(label);$box.prop('hidden',false);}
    function syncUrl(){if(!window.history||!window.URL)return;var url=new URL(window.location.href);if(state.category){url.searchParams.set('categoria',state.category);}else{url.searchParams.delete('categoria');}if(state.search){url.searchParams.set('busca',state.search);}else{url.searchParams.delete('busca');}if(state.type){url.searchParams.set('tipo',state.type);}else{url.searchParams.delete('tipo');}history.replaceState({},'',url.toString());}
    function apply(){var $page=page(),count=0;$page.find('[data-serben-market-card]').each(function(){var $card=$(this),search=String($card.attr('data-search')||''),cats=String($card.attr('data-categories')||''),type=String($card.attr('data-type')||'');var show=(!state.search||search.indexOf(state.search)!==-1)&&(!state.category||cats.indexOf('|'+state.category+'|')!==-1)&&(!state.type||type===state.type);$card.prop('hidden',!show);if(show)count++;});$page.find('[data-serben-market-count]').text(count);$page.find('[data-serben-market-empty]').prop('hidden',count!==0);updateNav();}
    function refreshChildren(index){var $panel=page().find('[data-serben-market-panel="'+index+'"]');$panel.find('[data-serben-market-filter]').each(function(){var token=String($(this).data('serben-market-filter')||'');if($(this).hasClass('is-all')){$(this).prop('hidden',false);return;}var count=page().find('[data-serben-market-card]').filter(function(){return String($(this).attr('data-categories')||'').indexOf('|'+token+'|')!==-1;}).length;$(this).prop('hidden',count===0);});}
    function showMain($button,skipUrl){var index=String($button.data('serben-market-parent')),slug=String($button.data('serben-market-slug')||''),name=String($button.data('serben-market-name')||'');state.category=slug;page().find('.serben-market-category').removeClass('is-active');$button.addClass('is-active');page().find('[data-serben-market-subtitle]').text(name);page().find('[data-serben-market-subcategories]').prop('hidden',false);page().find('[data-serben-market-panel]').each(function(){$(this).prop('hidden',String($(this).data('serben-market-panel'))!==index);});page().find('.serben-market-chip').removeClass('is-active');page().find('[data-serben-market-panel="'+index+'"] .is-all').addClass('is-active');refreshChildren(index);activeLabel(name);if(!skipUrl)syncUrl();apply();var el=$button.get(0),rail=page().find('[data-serben-market-categories]').get(0);if(el&&rail){var target=Math.max(0,el.offsetLeft-((rail.clientWidth-el.offsetWidth)/2));$(rail).stop(true).animate({scrollLeft:target},240,updateNav);}}
    function resetCategory(skipUrl){state.category='';var $page=page();$page.find('.serben-market-category').removeClass('is-active');$page.find('[data-serben-market-all]').addClass('is-active');$page.find('[data-serben-market-subcategories]').prop('hidden',true);$page.find('[data-serben-market-panel]').prop('hidden',true);$page.find('.serben-market-chip').removeClass('is-active');activeLabel('');if(!skipUrl)syncUrl();apply();}
    function updateNav(){var rail=page().find('[data-serben-market-categories]').get(0);if(!rail)return;var max=Math.max(0,rail.scrollWidth-rail.clientWidth);page().find('[data-serben-market-cat-prev]').prop('disabled',rail.scrollLeft<=2);page().find('[data-serben-market-cat-next]').prop('disabled',rail.scrollLeft>=max-2);}
    $(document).on('input','.serben-market [data-serben-market-search]',function(){var value=$(this).val()||'';page().find('[data-serben-market-clear]').prop('hidden',value==='');clearTimeout(timer);timer=setTimeout(function(){state.search=normalize(value);syncUrl();apply();},120);});
    $(document).on('click','.serben-market [data-serben-market-clear]',function(){page().find('[data-serben-market-search]').val('');$(this).prop('hidden',true);state.search='';syncUrl();apply();});
    $(document).on('click','.serben-market [data-serben-market-all]',function(){resetCategory(false);});
    $(document).on('click','.serben-market [data-serben-market-parent]',function(){showMain($(this),false);});
    $(document).on('click','.serben-market [data-serben-market-filter]',function(){var $b=$(this);state.category=String($b.data('serben-market-filter')||'');$b.closest('.serben-market-subpanel').find('.serben-market-chip').removeClass('is-active');$b.addClass('is-active');activeLabel($.trim($b.text()));syncUrl();apply();});
    $(document).on('click','.serben-market [data-serben-market-close-sub]',function(){page().find('[data-serben-market-subcategories]').prop('hidden',true);});
    $(document).on('click','.serben-market [data-serben-market-reset]',function(){state.search='';state.type='';page().find('[data-serben-market-search]').val('');resetCategory(false);});
    function moveCategories(direction){var rail=page().find('[data-serben-market-categories]').get(0);if(!rail)return;var amount=Math.max(260,Math.round(rail.clientWidth*.72)),max=Math.max(0,rail.scrollWidth-rail.clientWidth),target=Math.max(0,Math.min(max,rail.scrollLeft+(direction*amount)));if(typeof rail.scrollTo==='function'){rail.scrollTo({left:target,behavior:'smooth'});}else{rail.scrollLeft=target;}window.setTimeout(updateNav,320);}
    $(document).on('click','.serben-market [data-serben-market-cat-prev]',function(e){e.preventDefault();if(!this.disabled)moveCategories(-1);});
    $(document).on('click','.serben-market [data-serben-market-cat-next]',function(e){e.preventDefault();if(!this.disabled)moveCategories(1);});
    $(document).on('scroll','.serben-market [data-serben-market-categories]',updateNav);$(window).on('resize',updateNav);
    $(function(){var params=new URLSearchParams(window.location.search),slug=String(params.get('categoria')||''),search=String(params.get('busca')||params.get('q')||''),type=String(params.get('tipo')||'').toLowerCase();if(type==='físico'||type==='fisico'||type==='presencial')type='fisico';if(type==='online')state.type='online';else if(type==='fisico')state.type='fisico';if(search){state.search=normalize(search);page().find('[data-serben-market-search]').val(search);page().find('[data-serben-market-clear]').prop('hidden',false);}if(slug){var safe=slug.replace(/"/g,'');var $main=page().find('[data-serben-market-parent][data-serben-market-slug="'+safe+'"]');if($main.length){showMain($main.first(),true);}else{state.category=slug;activeLabel('Filtro selecionado');apply();}}else{apply();}setTimeout(updateNav,30);});
})(jQuery);
JS;
    }

}

Serben_Credenciados_Page_V9::init();

}
