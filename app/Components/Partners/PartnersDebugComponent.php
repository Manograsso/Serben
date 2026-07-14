<?php
namespace SerbenConnect\Components\Partners;

use SerbenConnect\Components\PublicComponent;
use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\PartnersProvider;

if (!defined('ABSPATH')) { exit; }

class PartnersDebugComponent extends PublicComponent
{
    protected $shortcode = 'serben_partners_debug';
    protected $title = 'Diagnóstico de parceiros';
    protected $category = 'Diagnóstico';
    protected $description = 'Exibe contagens, IDs e taxonomias do CPT de parceiros selecionado. Visível somente para administradores.';

    public function render(Member $member, array $atts = []): string
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        global $wpdb;
        $postType = PartnersProvider::postType();
        $registered = post_type_exists($postType);
        $counts = wp_count_posts($postType);
        $wpPublished = $counts && isset($counts->publish) ? (int) $counts->publish : 0;

        $sqlCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
            $postType
        ));
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID ASC LIMIT 200",
            $postType
        )));

        $provider = (new PartnersProvider())->all(['limit' => -1]);
        $items = (array) ($provider['items'] ?? []);
        $renderableIds = [];
        foreach ($items as $item) {
            if (is_object($item) && method_exists($item, 'id')) {
                $renderableIds[] = (int) $item->id();
            }
        }

        $allPostTypes = get_post_types([], 'objects');
        $similar = [];
        foreach ($allPostTypes as $slug => $object) {
            if (stripos($slug, 'parce') !== false || stripos((string) $object->label, 'parce') !== false) {
                $c = wp_count_posts($slug);
                $similar[$slug] = [
                    'label' => (string) $object->label,
                    'publish' => $c && isset($c->publish) ? (int) $c->publish : 0,
                ];
            }
        }

        $taxonomyObjects = get_object_taxonomies($postType, 'objects');
        $associatedTaxonomies = [];
        foreach ((array) $taxonomyObjects as $taxonomySlug => $taxonomyObject) {
            $taxonomySlug = (string) $taxonomySlug;
            $taxonomyTerms = get_terms([
                'taxonomy' => $taxonomySlug,
                'hide_empty' => false,
                'orderby' => 'name',
                'order' => 'ASC',
            ]);
            $termRows = [];
            if (!is_wp_error($taxonomyTerms)) {
                foreach ($taxonomyTerms as $term) {
                    $relatedPublished = 0;
                    $objectIds = get_objects_in_term((int) $term->term_id, $taxonomySlug);
                    if (!is_wp_error($objectIds)) {
                        foreach (array_unique(array_map('intval', (array) $objectIds)) as $objectId) {
                            $objectPost = get_post($objectId);
                            if ($objectPost && $objectPost->post_type === $postType && $objectPost->post_status === 'publish') {
                                $relatedPublished++;
                            }
                        }
                    }
                    $termRows[] = [
                        'term_id' => (int) $term->term_id,
                        'name' => (string) $term->name,
                        'slug' => (string) $term->slug,
                        'native_count' => (int) $term->count,
                        'published_partners_related' => $relatedPublished,
                    ];
                }
            }
            $associatedTaxonomies[$taxonomySlug] = [
                'label' => isset($taxonomyObject->label) ? (string) $taxonomyObject->label : $taxonomySlug,
                'public' => !empty($taxonomyObject->public),
                'hierarchical' => !empty($taxonomyObject->hierarchical),
                'show_in_rest' => !empty($taxonomyObject->show_in_rest),
                'terms' => $termRows,
            ];
        }

        $data = [
            'site_url' => site_url(),
            'blog_id' => get_current_blog_id(),
            'cpt_slug_testado' => $postType,
            'cpt_registrado' => $registered,
            'wp_count_posts_publish' => $wpPublished,
            'sql_direto_publish' => $sqlCount,
            'provider_total' => (int) ($provider['total'] ?? 0),
            'provider_published_count' => (int) ($provider['published_count'] ?? 0),
            'provider_items_renderizaveis' => count($renderableIds),
            'primeiros_ids_sql' => array_slice($ids, 0, 30),
            'primeiros_ids_provider' => array_slice($renderableIds, 0, 30),
            'post_types_semelhantes' => $similar,
            'associated_taxonomies' => $associatedTaxonomies,
            'plugin_version' => defined('SERBEN_CONNECT_VERSION') ? SERBEN_CONNECT_VERSION : '',
        ];

        return '<div class="serben-debug-box"><p><strong>Diagnóstico de parceiros</strong></p><pre>' . esc_html(wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></div>';
    }
}
