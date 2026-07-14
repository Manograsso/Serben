<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnerCategoriesDebugComponent extends PublicComponent
{
    protected $shortcode = 'serben_partner_categories_debug';
    protected $title = 'Diagnóstico de categorias de parceiros';
    protected $category = 'Diagnóstico';
    protected $description = 'Exibe os valores reais da taxonomia e do meta field categoria. Somente administradores.';

    public function render(Member $member, array $atts = []): string
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        global $wpdb;
        $postType = PartnersProvider::postType();
        $taxonomy = PartnersProvider::categoryTaxonomy();

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        $termRows = [];
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $related = get_objects_in_term((int) $term->term_id, $taxonomy);
                if (is_wp_error($related)) { $related = []; }
                $publishedRelated = 0;
                foreach ((array) $related as $postId) {
                    if (get_post_status((int) $postId) === 'publish' && get_post_type((int) $postId) === $postType) {
                        $publishedRelated++;
                    }
                }
                $termRows[] = [
                    'term_id' => (int) $term->term_id,
                    'name' => (string) $term->name,
                    'slug' => (string) $term->slug,
                    'native_count' => (int) $term->count,
                    'published_partners_related' => $publishedRelated,
                ];
            }
        }

        $postIds = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID ASC",
            $postType
        )));

        $samples = [];
        $metaShapes = [];
        $nonEmptyMeta = 0;
        $nativeRelations = 0;
        foreach ($postIds as $postId) {
            $raw = get_post_meta($postId, 'categoria', true);
            $allRaw = get_post_meta($postId, 'categoria', false);
            $native = wp_get_post_terms($postId, $taxonomy, ['fields' => 'all']);
            if (!is_wp_error($native) && $native) { $nativeRelations++; }
            if ($raw !== '' && $raw !== null && $raw !== []) { $nonEmptyMeta++; }

            $type = gettype($raw);
            if (is_array($raw)) {
                $type .= ':array';
                if (isset($raw['value'])) { $type .= ':value'; }
                if (isset($raw['label'])) { $type .= ':label'; }
            } elseif (is_string($raw)) {
                $trim = trim($raw);
                if (is_serialized($trim)) { $type .= ':serialized'; }
                elseif ($trim !== '' && (($trim[0] ?? '') === '[' || ($trim[0] ?? '') === '{')) { $type .= ':json-like'; }
                elseif (strpos($trim, ',') !== false) { $type .= ':csv'; }
                elseif (ctype_digit($trim)) { $type .= ':numeric-string'; }
            }
            $metaShapes[$type] = ($metaShapes[$type] ?? 0) + 1;

            if (count($samples) < 25) {
                $samples[] = [
                    'post_id' => $postId,
                    'title' => get_the_title($postId),
                    'categoria_get_post_meta_true' => $raw,
                    'categoria_all_rows' => $allRaw,
                    'native_terms' => is_wp_error($native) ? ['error' => $native->get_error_message()] : array_map(static function ($term) {
                        return [
                            'term_id' => (int) $term->term_id,
                            'name' => (string) $term->name,
                            'slug' => (string) $term->slug,
                        ];
                    }, $native),
                ];
            }
        }

        $providerCategories = (new PartnersProvider())->categories();

        $allTaxonomies = [];
        foreach ((array) get_object_taxonomies($postType, 'objects') as $taxonomySlug => $taxonomyObject) {
            $taxonomySlug = (string) $taxonomySlug;
            $termsForTaxonomy = get_terms([
                'taxonomy' => $taxonomySlug,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);
            $termSummaries = [];
            if (!is_wp_error($termsForTaxonomy)) {
                foreach ($termsForTaxonomy as $term) {
                    $related = [];
                    $objectIds = get_objects_in_term((int) $term->term_id, $taxonomySlug);
                    if (!is_wp_error($objectIds)) {
                        foreach (array_unique(array_map('intval', (array) $objectIds)) as $objectId) {
                            $objectPost = get_post($objectId);
                            if ($objectPost && $objectPost->post_type === $postType && $objectPost->post_status === 'publish') {
                                $related[] = $objectId;
                            }
                        }
                    }
                    $termSummaries[] = [
                        'term_id' => (int) $term->term_id,
                        'name' => (string) $term->name,
                        'slug' => (string) $term->slug,
                        'published_partner_count' => count($related),
                        'sample_partner_ids' => array_slice($related, 0, 10),
                    ];
                }
            }
            $allTaxonomies[$taxonomySlug] = [
                'label' => isset($taxonomyObject->label) ? (string) $taxonomyObject->label : $taxonomySlug,
                'hierarchical' => !empty($taxonomyObject->hierarchical),
                'show_in_rest' => !empty($taxonomyObject->show_in_rest),
                'terms' => $termSummaries,
            ];
        }

        $data = [
            'plugin_version' => defined('SERBEN_CONNECT_VERSION') ? SERBEN_CONNECT_VERSION : '',
            'post_type' => $postType,
            'taxonomy' => $taxonomy,
            'published_partners' => count($postIds),
            'registered_taxonomy_terms' => count($termRows),
            'partners_with_native_taxonomy_relation' => $nativeRelations,
            'partners_with_nonempty_categoria_meta' => $nonEmptyMeta,
            'categoria_meta_value_shapes' => $metaShapes,
            'taxonomy_terms' => $termRows,
            'provider_categories' => $providerCategories,
            'all_associated_taxonomies' => $allTaxonomies,
            'partner_samples' => $samples,
        ];

        return '<div class="serben-debug-box"><p><strong>Diagnóstico de categorias dos parceiros</strong></p><pre>' . esc_html(wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }
}
