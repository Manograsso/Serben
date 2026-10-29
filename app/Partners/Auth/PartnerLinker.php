<?php
namespace SerbenConnect\Partners\Auth;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class PartnerLinker
{
    public function link(int $userId, string $cnpj, array $store = []): array
    {
        $cnpj = preg_replace('/\D+/', '', $cnpj);
        $postType = sanitize_key((string) Settings::get('partners_post_type', 'serben_parceiro'));
        $ids = get_posts([
            'post_type' => $postType,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        $matches = [];
        foreach ($ids as $candidateId) {
            foreach (['cnpj_do_parceiro','_serben_cnpj'] as $field) {
                $candidateCnpj = preg_replace('/\D+/', '', (string)get_post_meta((int)$candidateId, $field, true));
                if ($candidateCnpj !== '' && $candidateCnpj === $cnpj) {
                    $matches[(int)$candidateId] = true;
                    break;
                }
            }
        }
        if (count($matches) > 1) {
            return ['ok'=>false,'post_id'=>0,'message'=>'Há mais de um parceiro com este CNPJ. Resolva a duplicidade antes de vincular.'];
        }
        $postId = $matches ? (int)array_key_first($matches) : 0;

        if (!$postId) {
            return ['ok' => false, 'post_id' => 0, 'message' => 'Nenhum parceiro foi encontrado com este CNPJ.'];
        }
        $storeId = (int)($store['idLoja'] ?? $store['id_loja'] ?? $store['id'] ?? 0);
        update_user_meta($userId, 'serben_partner_post_id', $postId);
        update_user_meta($userId, 'serben_partner_cnpj', $cnpj);
        if ($storeId) { update_user_meta($userId, 'serben_store_id', $storeId); }
        // Keep the existing primary user when a company has multiple approved contacts.
        $primary = (int)get_post_meta($postId, '_serben_wp_user_id', true);
        if (!$primary || !get_userdata($primary)) {
            update_post_meta($postId, '_serben_wp_user_id', $userId);
        }
        if ($storeId) { update_post_meta($postId, '_serben_store_id', $storeId); }
        return ['ok' => true, 'post_id' => $postId, 'store_id' => $storeId, 'message' => 'Parceiro vinculado com sucesso.'];
    }
}
