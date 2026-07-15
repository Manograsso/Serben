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

        $postId = 0;
        foreach ($ids as $candidateId) {
            $candidateCnpj = preg_replace('/\D+/', '', (string) get_post_meta((int) $candidateId, 'cnpj_do_parceiro', true));
            if ($candidateCnpj === $cnpj) {
                $postId = (int) $candidateId;
                break;
            }
        }

        if (!$postId) {
            return ['ok' => false, 'post_id' => 0, 'message' => 'Nenhum parceiro foi encontrado com este CNPJ.'];
        }
        $storeId = (int)($store['idLoja'] ?? $store['id_loja'] ?? $store['id'] ?? 0);
        update_user_meta($userId, 'serben_partner_post_id', $postId);
        update_user_meta($userId, 'serben_partner_cnpj', $cnpj);
        if ($storeId) { update_user_meta($userId, 'serben_store_id', $storeId); }
        update_post_meta($postId, '_serben_wp_user_id', $userId);
        if ($storeId) { update_post_meta($postId, '_serben_store_id', $storeId); }
        return ['ok' => true, 'post_id' => $postId, 'store_id' => $storeId, 'message' => 'Parceiro vinculado com sucesso.'];
    }
}
