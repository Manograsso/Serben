<?php
namespace SerbenConnect\Providers;

use SerbenConnect\Domain\Partner;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

class PartnersProvider
{
    public const DEFAULT_POST_TYPE = 'serben_parceiro';

    public static function postType(): string
    {
        $postType = sanitize_key((string) Settings::get('partners_post_type', self::DEFAULT_POST_TYPE));
        return $postType !== '' ? $postType : self::DEFAULT_POST_TYPE;
    }

    public static function categoryTaxonomy(): string
    {
        $taxonomy = sanitize_key((string) Settings::get('partners_category_taxonomy', 'serben_categoria'));
        return $taxonomy !== '' ? $taxonomy : 'serben_categoria';
    }

    public static function localityTaxonomy(): string
    {
        $taxonomy = sanitize_key((string) Settings::get('partners_locality_taxonomy', 'localidade'));
        return $taxonomy !== '' ? $taxonomy : 'localidade';
    }

    public static function benefitTaxonomy(): string
    {
        $taxonomy = sanitize_key((string) Settings::get('partners_benefit_taxonomy', 'tipo-de-beneficio'));
        return $taxonomy !== '' ? $taxonomy : 'tipo-de-beneficio';
    }

    public function all(array $filters = []): array
    {
        global $wpdb;

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 12;
        $limit = $limit === -1 ? -1 : max(1, $limit);
        $paged = isset($filters['paged']) ? max(1, (int) $filters['paged']) : 1;
        $orderby = strtolower((string) ($filters['orderby'] ?? 'title'));
        $order = strtoupper((string) ($filters['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $search = sanitize_text_field((string) ($filters['search'] ?? ''));

        // Consulta direta para não sofrer interferência de pre_get_posts,
        // JetEngine Query Builder, Elementor, WPML/Polylang ou outros hooks.
        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            self::postType()
        );
        $postIds = array_map('intval', (array) $wpdb->get_col($sql));

        $partners = [];
        foreach ($postIds as $postId) {
            $post = get_post($postId);
            if (!$post instanceof \WP_Post) { continue; }

            $partner = $this->mapPost($post);
            if (!$this->matchesFilters($partner, $filters, $search)) { continue; }
            $partners[] = $partner;
        }

        usort($partners, function (Partner $a, Partner $b) use ($orderby, $order): int {
            $left = $this->sortValue($a, $orderby);
            $right = $this->sortValue($b, $orderby);
            $result = is_numeric($left) && is_numeric($right)
                ? ($left <=> $right)
                : strnatcasecmp((string) $left, (string) $right);
            return $order === 'DESC' ? -$result : $result;
        });

        $total = count($partners);
        if ($limit !== -1) {
            $offset = ($paged - 1) * $limit;
            $partners = array_slice($partners, $offset, $limit);
            $pages = (int) ceil($total / $limit);
        } else {
            $pages = $total > 0 ? 1 : 0;
        }

        return [
            'ok' => true,
            'items' => array_values($partners),
            'total' => $total,
            'pages' => $pages,
            'published_count' => count($postIds),
            'source' => 'wordpress-direct',
        ];
    }

    private function matchesFilters(Partner $partner, array $filters, string $search): bool
    {
        if ($search !== '') {
            $haystack = implode(' ', [
                $partner->name(),
                (string) $partner->get('descricao_curta', ''),
                wp_strip_all_tags((string) $partner->get('descricao_completa', '')),
                (string) $partner->get('cidade', ''),
                (string) $partner->get('estado', ''),
            ]);
            if (stripos(remove_accents($haystack), remove_accents($search)) === false) { return false; }
        }

        if (!$this->matchesTaxonomy($partner, 'partner_categories', $filters['category'] ?? '')) { return false; }
        if (!$this->matchesTaxonomy($partner, 'partner_localities', $filters['locality'] ?? '')) { return false; }
        if (!$this->matchesTaxonomy($partner, 'partner_benefit_types', $filters['benefit_type'] ?? '')) { return false; }

        $boolMap = [
            'featured' => 'parceiro_destaque',
            'cashback' => 'tem_cashback',
            'discount' => 'tem_desconto',
            'accepts_bume' => 'aceita_bume',
        ];
        foreach ($boolMap as $filterKey => $field) {
            $value = $filters[$filterKey] ?? '';
            if ($value === '' || $value === null) { continue; }
            $expected = in_array(strtolower((string) $value), ['1', 'yes', 'sim', 'true', 'on'], true);
            if ((bool) $partner->get($field, false) !== $expected) { return false; }
        }

        if (!empty($filters['city']) && stripos(remove_accents((string) $partner->get('cidade', '')), remove_accents((string) $filters['city'])) === false) { return false; }
        if (!empty($filters['state']) && strtoupper(trim((string) $partner->get('estado', ''))) !== strtoupper(trim((string) $filters['state']))) { return false; }

        return true;
    }

    private function matchesTaxonomy(Partner $partner, string $taxonomy, $requested): bool
    {
        if ($requested === '' || $requested === null) { return true; }
        $wanted = array_values(array_filter(array_map('sanitize_title', is_array($requested) ? $requested : explode(',', (string) $requested))));
        if (!$wanted) { return true; }
        $terms = (array) $partner->get($taxonomy, []);
        $actual = [];
        foreach ($terms as $term) {
            if (is_array($term) && !empty($term['slug'])) { $actual[] = sanitize_title((string) $term['slug']); }
        }
        return (bool) array_intersect($wanted, $actual);
    }

    private function sortValue(Partner $partner, string $orderby)
    {
        switch ($orderby) {
            case 'date':
                $post = get_post($partner->id());
                return $post ? strtotime((string) $post->post_date_gmt) : 0;
            case 'id':
                return $partner->id();
            case 'featured':
                return $partner->isFeatured() ? 1 : 0;
            case 'cashback':
                return (float) $partner->get('cashback_associado', 0);
            case 'title':
            default:
                return $partner->name();
        }
    }

    public function find(int $postId): ?Partner
    {
        $post = get_post($postId);
        if (!$post || $post->post_type !== self::postType() || $post->post_status !== 'publish') {
            return null;
        }
        return $this->mapPost($post);
    }

    public function current(): ?Partner
    {
        if (!is_singular(self::postType())) { return null; }
        return $this->find((int) get_queried_object_id());
    }

    public function categories(): array
    {
        $catalog = [];

        // Inclui todos os termos cadastrados na taxonomia, mesmo quando o
        // contador nativo está desatualizado ou a relação foi salva apenas em meta.
        $terms = get_terms([
            'taxonomy' => self::categoryTaxonomy(),
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $slug = sanitize_title((string) $term->slug);
                if ($slug === '') { continue; }
                $catalog[$slug] = [
                    'id' => (int) $term->term_id,
                    'nome' => (string) $term->name,
                    'slug' => $slug,
                    'count' => 0,
                    'source' => 'taxonomy',
                ];
            }
        }

        // Calcula os vínculos reais usando a mesma normalização adotada pelo
        // filtro: taxonomia nativa + meta field JetEngine `categoria`.
        $result = $this->all(['limit' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        foreach (($result['items'] ?? []) as $partner) {
            if (!$partner instanceof Partner) { continue; }
            foreach ($partner->categories() as $category) {
                if (!is_array($category)) { continue; }
                $slug = sanitize_title((string) ($category['slug'] ?? ''));
                if ($slug === '') { continue; }
                if (!isset($catalog[$slug])) {
                    $catalog[$slug] = [
                        'id' => (int) ($category['id'] ?? 0),
                        'nome' => (string) ($category['name'] ?? $category['nome'] ?? $slug),
                        'slug' => $slug,
                        'count' => 0,
                        'source' => (string) ($category['source'] ?? 'meta'),
                    ];
                }
                $catalog[$slug]['count']++;
            }
        }

        uasort($catalog, static function (array $a, array $b): int {
            return strnatcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return ['ok' => true, 'items' => array_values($catalog), 'source' => 'wordpress-unified'];
    }

    public function field(Partner $partner, string $field)
    {
        return $partner->get($field);
    }

    private function mapPost(\WP_Post $post): Partner
    {
        $meta = function (string $key) use ($post) { return get_post_meta($post->ID, $key, true); };
        $name = trim((string) $meta('nome_fantasia')) ?: get_the_title($post);
        $short = trim((string) $meta('descricao_curta')) ?: (string) ($post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 28));
        $full = (string) $meta('descricao_completa');
        if ($full === '') { $full = (string) $post->post_content; }

        $data = [
            'id' => (int) $post->ID,
            'permalink' => get_permalink($post),
            'nome_fantasia' => $name,
            'whatsapp' => (string) $meta('whatsapp'),
            'site' => (string) $meta('site'),
            'instagram' => (string) $meta('instagram'),
            'cidade' => (string) $meta('cidade'),
            'estado' => strtoupper((string) $meta('estado')),
            'cep' => (string) $meta('cep'),
            'descricao_curta' => $short,
            'descricao_completa' => $full,
            'logo' => $this->mediaUrl($meta('logo'), get_post_thumbnail_id($post)),
            'foto_capa' => $this->mediaUrl($meta('foto_capa'), get_post_thumbnail_id($post)),
            'galeria' => $this->galleryUrls($meta('galeria')),
            'categoria' => $meta('categoria'),
            'tipo' => $meta('tipo'),
            'plano_parceiro' => $this->normalizeList($meta('plano_parceiro')),
            'aceita_bume' => $this->toBool($meta('aceita_bume')),
            'tem_cashback' => $this->toBool($meta('tem_cashback')),
            'tem_desconto' => $this->toBool($meta('tem_desconto')),
            'distribui_bume' => $this->toBool($meta('distribui_bume')),
            'cashback_geral' => $this->toNumber($meta('cashback_geral')),
            'percentual_repasse' => $this->toNumber($meta('percentual_repasse')),
            'cashback_associado' => $this->toNumber($meta('cashback_associado')),
            'link_afiliado' => (string) $meta('link_afiliado'),
            'parceiro_destaque' => $this->toBool($meta('parceiro_destaque')),
            'partner_categories' => $this->mergeCategoryData($post->ID, $meta('categoria')),
            'categoria-parceiro' => $this->mergeCategoryData($post->ID, $meta('categoria')),
            'partner_localities' => $this->termData($post->ID, self::localityTaxonomy()),
            'localidade' => $this->termData($post->ID, self::localityTaxonomy()),
            'partner_benefit_types' => $this->termData($post->ID, self::benefitTaxonomy()),
            'tipo-de-beneficio' => $this->termData($post->ID, self::benefitTaxonomy()),
        ];

        return new Partner((int) $post->ID, $data);
    }


    /**
     * Une relações nativas da taxonomia com o campo JetEngine `categoria`.
     * Importações podem salvar apenas IDs/nomes/slugs no post meta, sem criar
     * a relação em term_relationships. O plugin normaliza ambos os formatos.
     */
    private function mergeCategoryData(int $postId, $metaValue): array
    {
        $items = [];

        foreach ($this->termData($postId, self::categoryTaxonomy()) as $term) {
            $slug = sanitize_title((string) ($term['slug'] ?? ''));
            if ($slug === '') { continue; }
            $term['source'] = 'taxonomy';
            $items[$slug] = $term;
        }

        foreach ($this->categoryDataFromMeta($metaValue) as $category) {
            $slug = sanitize_title((string) ($category['slug'] ?? ''));
            if ($slug === '') { continue; }
            if (!isset($items[$slug])) {
                $items[$slug] = $category;
            }
        }

        return array_values($items);
    }

    private function categoryDataFromMeta($value): array
    {
        $values = $this->flattenCategoryValues($value);
        $categories = [];

        foreach ($values as $item) {
            $term = null;
            $id = 0;
            $name = '';
            $slug = '';

            if (is_array($item)) {
                $id = (int) ($item['term_id'] ?? $item['id'] ?? $item['value'] ?? 0);
                $name = trim((string) ($item['name'] ?? $item['label'] ?? $item['title'] ?? ''));
                $slug = sanitize_title((string) ($item['slug'] ?? ''));
            } elseif (is_object($item)) {
                $id = (int) ($item->term_id ?? $item->id ?? 0);
                $name = trim((string) ($item->name ?? $item->label ?? ''));
                $slug = sanitize_title((string) ($item->slug ?? ''));
            } elseif (is_numeric($item)) {
                $id = (int) $item;
            } else {
                $raw = trim(wp_strip_all_tags((string) $item));
                if ($raw === '') { continue; }
                $slug = sanitize_title($raw);
                $name = $raw;
            }

            if ($id > 0) {
                $candidate = get_term($id, self::categoryTaxonomy());
                if ($candidate && !is_wp_error($candidate)) { $term = $candidate; }
            }
            if (!$term && $slug !== '') {
                $candidate = get_term_by('slug', $slug, self::categoryTaxonomy());
                if ($candidate && !is_wp_error($candidate)) { $term = $candidate; }
            }
            if (!$term && $name !== '') {
                $candidate = get_term_by('name', $name, self::categoryTaxonomy());
                if ($candidate && !is_wp_error($candidate)) { $term = $candidate; }
            }

            if ($term) {
                $id = (int) $term->term_id;
                $name = (string) $term->name;
                $slug = sanitize_title((string) $term->slug);
            } else {
                if ($slug === '') { $slug = sanitize_title($name); }
                if ($name === '') { $name = $slug; }
            }

            if ($slug === '') { continue; }
            $categories[$slug] = [
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'source' => 'meta',
            ];
        }

        return array_values($categories);
    }

    private function flattenCategoryValues($value): array
    {
        if ($value === '' || $value === null || $value === false) { return []; }
        $value = maybe_unserialize($value);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = preg_split('/\s*[,;|]\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
            }
        }

        if (!is_array($value)) { return [$value]; }

        // Arrays associativos que representam uma única opção do JetEngine.
        if (isset($value['term_id']) || isset($value['id']) || isset($value['value']) || isset($value['slug']) || isset($value['name']) || isset($value['label'])) {
            return [$value];
        }

        $flat = [];
        foreach ($value as $item) {
            if (is_array($item) && !(isset($item['term_id']) || isset($item['id']) || isset($item['value']) || isset($item['slug']) || isset($item['name']) || isset($item['label']))) {
                $flat = array_merge($flat, $this->flattenCategoryValues($item));
            } else {
                $flat[] = $item;
            }
        }
        return $flat;
    }

    private function addTaxFilter(array &$query, string $taxonomy, $value): void
    {
        if ($value === '' || $value === null) { return; }
        $values = array_filter(array_map('sanitize_title', is_array($value) ? $value : explode(',', (string) $value)));
        if ($values) { $query[] = ['taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $values]; }
    }

    private function addBooleanMetaFilter(array &$query, string $key, $value): void
    {
        if ($value === '' || $value === null) { return; }
        $yes = in_array(strtolower((string) $value), ['1', 'yes', 'sim', 'true', 'on'], true);
        if ($yes) {
            $query[] = ['key' => $key, 'value' => ['1', 'yes', 'sim', 'true', 'on'], 'compare' => 'IN'];
        } else {
            $query[] = ['relation' => 'OR', ['key' => $key, 'compare' => 'NOT EXISTS'], ['key' => $key, 'value' => ['0', '', 'no', 'nao', 'false', 'off'], 'compare' => 'IN']];
        }
    }

    private function mediaUrl($value, int $fallbackId = 0): string
    {
        if (is_array($value)) {
            $value = $value['id'] ?? $value['url'] ?? reset($value);
        }
        if (is_numeric($value)) {
            $url = wp_get_attachment_image_url((int) $value, 'full');
            if ($url) { return $url; }
        }
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) { return $value; }
        if ($fallbackId) {
            $url = wp_get_attachment_image_url($fallbackId, 'full');
            return $url ?: '';
        }
        return '';
    }

    private function galleryUrls($value): array
    {
        $items = $this->normalizeList($value);
        $urls = [];
        foreach ($items as $item) {
            $url = $this->mediaUrl($item);
            if ($url) { $urls[] = $url; }
        }
        return array_values(array_unique($urls));
    }

    private function normalizeList($value): array
    {
        if ($value === '' || $value === null) { return []; }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) { $value = $decoded; }
            else { $value = preg_split('/\s*,\s*/', $value); }
        }
        if (!is_array($value)) { $value = [$value]; }
        return array_values(array_filter($value, static function ($item) { return $item !== '' && $item !== null; }));
    }

    private function termData(int $postId, string $taxonomy): array
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (!$terms || is_wp_error($terms)) { return []; }
        return array_map(static function ($term) {
            return ['id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
        }, $terms);
    }

    private function toBool($value): bool
    {
        if (is_array($value)) { $value = reset($value); }
        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'sim', 'true', 'on'], true);
    }

    private function toNumber($value): float
    {
        if (is_array($value)) { $value = reset($value); }
        $value = str_replace(['%', ' '], '', (string) $value);
        if (strpos($value, ',') !== false && strpos($value, '.') !== false) { $value = str_replace('.', '', $value); }
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
