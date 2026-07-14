<?php
namespace SerbenConnect\Core;

use SerbenConnect\Components\ComponentEngine;
use SerbenConnect\Providers\PartnersProvider;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class EnvironmentInspector
{
    public function inspect(): array
    {
        $postType = PartnersProvider::postType();
        $categoryTaxonomy = PartnersProvider::categoryTaxonomy();
        $localityTaxonomy = PartnersProvider::localityTaxonomy();
        $benefitTaxonomy = PartnersProvider::benefitTaxonomy();
        $counts = post_type_exists($postType) ? wp_count_posts($postType) : null;

        return [
            'plugin' => [
                'version' => defined('SERBEN_CONNECT_VERSION') ? SERBEN_CONNECT_VERSION : '',
                'php' => PHP_VERSION,
                'wordpress' => get_bloginfo('version'),
                'site_url' => site_url(),
            ],
            'integrations' => [
                'elementor' => defined('ELEMENTOR_VERSION'),
                'jetengine' => defined('JET_ENGINE_VERSION') || class_exists('Jet_Engine'),
                'woocommerce' => class_exists('WooCommerce'),
            ],
            'api' => [
                'base_url_configured' => Settings::get('base_url') !== '',
                'api_key_configured' => Settings::get('api_key') !== '',
                'identifier_configured' => Settings::get('identifier') !== '',
                'store_id_configured' => Settings::get('id_loja') !== '',
            ],
            'partners' => [
                'post_type' => $postType,
                'post_type_exists' => post_type_exists($postType),
                'published' => $counts && isset($counts->publish) ? (int) $counts->publish : 0,
                'category_taxonomy' => $categoryTaxonomy,
                'category_taxonomy_exists' => taxonomy_exists($categoryTaxonomy),
                'category_terms' => taxonomy_exists($categoryTaxonomy)
                    ? (int) wp_count_terms(['taxonomy' => $categoryTaxonomy, 'hide_empty' => false])
                    : 0,
                'locality_taxonomy' => $localityTaxonomy,
                'locality_taxonomy_exists' => taxonomy_exists($localityTaxonomy),
                'benefit_taxonomy' => $benefitTaxonomy,
                'benefit_taxonomy_exists' => taxonomy_exists($benefitTaxonomy),
            ],
            'components' => [
                'registered' => count((new ComponentEngine())->catalog()),
            ],
            'cache' => [
                'ttl_seconds' => (int) Settings::get('cache_ttl', 600),
                'external_object_cache' => wp_using_ext_object_cache(),
            ],
        ];
    }
}
