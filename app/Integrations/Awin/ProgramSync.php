<?php
namespace SerbenConnect\Integrations\Awin;

use SerbenConnect\Providers\PartnersProvider;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class ProgramSync
{
    private $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client();
    }

    public function programs(bool $forceRefresh = false): array
    {
        $publisher = (string) Settings::get('awin_publisher_id');
        if ($publisher === '') {
            return ['ok' => false, 'message' => 'Publisher ID não configurado.', 'programs' => []];
        }

        $cacheKey = 'serben_awin_programs_' . md5($publisher);
        if (!$forceRefresh) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return ['ok' => true, 'programs' => $cached, 'cached' => true];
            }
        }

        $res = $this->client->get('publishers/' . $publisher . '/programmes', ['relationship' => 'joined']);
        if (empty($res['ok']) || !is_array($res['body'])) {
            return ['ok' => false, 'message' => 'Falha ao consultar programas Awin.', 'programs' => [], 'response' => $res];
        }

        $programs = array_values(array_filter($res['body'], 'is_array'));
        set_transient($cacheKey, $programs, 5 * MINUTE_IN_SECONDS);
        return ['ok' => true, 'programs' => $programs, 'cached' => false];
    }

    /**
     * Prepara uma sincronização administrativa em lotes. A lista é atualizada
     * uma única vez e fica em cache para que as chamadas seguintes não precisem
     * consultar a Awin novamente.
     */
    public function startBatch(): array
    {
        $list = $this->programs(true);
        if (empty($list['ok'])) {
            return $list;
        }
        return [
            'ok' => true,
            'total' => count((array) ($list['programs'] ?? [])),
            'batch_size' => 3,
        ];
    }

    /**
     * Processa somente uma pequena fatia da lista. Isso evita Gateway Timeout
     * em hospedagens com limite curto de execução de requisições administrativas.
     */
    public function syncBatch(int $offset, int $limit = 3): array
    {
        $offset = max(0, $offset);
        $limit = max(1, min(5, $limit));
        $list = $this->programs(false);
        if (empty($list['ok'])) {
            return $list;
        }

        $programs = array_values((array) ($list['programs'] ?? []));
        $total = count($programs);
        $slice = array_slice($programs, $offset, $limit);
        $created = $updated = $skipped = $activated = $deactivated = 0;
        $processed = 0;
        $items = [];

        foreach ($slice as $program) {
            if (!is_array($program)) { $skipped++; continue; }
            $result = $this->syncProgram($program, 0, true);
            $items[] = [
                'advertiser_id' => (int) ($program['id'] ?? 0),
                'name' => (string) ($program['name'] ?? ''),
                'ok' => !empty($result['ok']),
                'action' => (string) ($result['action'] ?? ''),
                'post_id' => (int) ($result['post_id'] ?? 0),
            ];
            if (empty($result['ok'])) { $skipped++; continue; }
            $processed++;
            if (($result['action'] ?? '') === 'created') { $created++; }
            if (($result['action'] ?? '') === 'updated') { $updated++; }
            if (($result['status_action'] ?? '') === 'activated') { $activated++; }
            if (($result['status_action'] ?? '') === 'deactivated') { $deactivated++; }
        }

        $next = min($total, $offset + count($slice));
        $done = $next >= $total;
        $missing = 0;
        if ($done) {
            $seen = [];
            foreach ($programs as $program) {
                $id = is_array($program) ? (int) ($program['id'] ?? 0) : 0;
                if ($id > 0) { $seen[] = $id; }
            }
            $missing = $this->deactivateMissing($seen);
            $deactivated += $missing;
        }

        return [
            'ok' => true,
            'offset' => $offset,
            'next_offset' => $next,
            'total' => $total,
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'activated' => $activated,
            'deactivated' => $deactivated,
            'deactivated_missing' => $missing,
            'skipped' => $skipped,
            'done' => $done,
            'items' => $items,
        ];
    }

    public function sync(): array
    {
        $list = $this->programs(true);
        if (empty($list['ok'])) {
            return $list;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $activated = 0;
        $deactivated = 0;
        $items = 0;
        $seenAdvertiserIds = [];

        foreach ($list['programs'] as $program) {
            $advertiserId = (int) ($program['id'] ?? 0);
            if ($advertiserId > 0) { $seenAdvertiserIds[] = $advertiserId; }

            $result = $this->syncProgram($program, 0, true);
            if (empty($result['ok'])) {
                $skipped++;
                continue;
            }
            $items++;
            if (($result['action'] ?? '') === 'created') { $created++; }
            if (($result['action'] ?? '') === 'updated') { $updated++; }
            if (($result['status_action'] ?? '') === 'activated') { $activated++; }
            if (($result['status_action'] ?? '') === 'deactivated') { $deactivated++; }
        }

        $missing = $this->deactivateMissing($seenAdvertiserIds);
        $deactivated += $missing;

        return [
            'ok' => true,
            'total' => $items,
            'created' => $created,
            'updated' => $updated,
            'activated' => $activated,
            'deactivated' => $deactivated,
            'deactivated_missing' => $missing,
            'skipped' => $skipped,
        ];
    }

    public function syncOne(int $advertiserId): array
    {
        $program = $this->programById($advertiserId);
        if (!$program) {
            $deactivated = $this->deactivateAdvertiser($advertiserId, 'missing');
            return [
                'ok' => false,
                'message' => 'Programa Awin não encontrado para este Publisher. Se havia um parceiro vinculado, ele foi desativado.',
                'advertiser_id' => $advertiserId,
                'deactivated' => $deactivated,
            ];
        }

        return $this->syncProgram($program, 0, true);
    }

    /**
     * Diagnóstico seguro para a tela administrativa. Não altera conteúdo.
     */
    public function inspectOne(int $advertiserId): array
    {
        $program = $this->programById($advertiserId);
        if (!$program) {
            return ['ok' => false, 'message' => 'Programa Awin não encontrado.', 'advertiser_id' => $advertiserId];
        }
        $match = $this->findMatch($program);
        return [
            'ok' => true,
            'advertiser_id' => $advertiserId,
            'program' => $program,
            'match' => $match,
            'candidates' => $this->candidateDetails($program),
        ];
    }

    /**
     * Define explicitamente o post canônico para um Advertiser ID e repara os
     * vínculos existentes. Esta ação é deliberadamente administrativa.
     */
    public function bindCanonical(int $advertiserId, int $postId): array
    {
        $program = $this->programById($advertiserId, true);
        if (!$program) {
            return ['ok' => false, 'message' => 'Programa Awin não encontrado.', 'advertiser_id' => $advertiserId];
        }
        if (!$this->isPartnerPost($postId)) {
            return ['ok' => false, 'message' => 'O Post ID informado não é um parceiro reconhecido.', 'advertiser_id' => $advertiserId, 'post_id' => $postId];
        }

        // Marca primeiro o vínculo explícito; isso faz com que futuras sincronizações
        // nunca substituam silenciosamente esta decisão administrativa.
        update_post_meta($postId, 'awin_advertiser_id', $advertiserId);
        update_post_meta($postId, 'awin_canonical', '1');
        update_post_meta($postId, 'awin_binding_source', 'manual');
        delete_post_meta($postId, 'awin_duplicate_of');
        delete_post_meta($postId, 'awin_duplicate_advertiser_id');

        $candidateIds = $this->allRelatedCandidateIds($program, $advertiserId);
        $duplicates = array_values(array_filter($candidateIds, static function ($id) use ($postId) {
            return (int) $id !== $postId;
        }));
        $quarantined = $this->quarantineDuplicates($postId, $advertiserId, $duplicates);

        $result = $this->syncProgram($program, $postId, false);
        $result['binding_action'] = 'manual_canonical';
        $result['duplicates_quarantined'] = array_values(array_unique(array_merge(
            (array) ($result['duplicates_quarantined'] ?? []),
            $quarantined
        )));
        return $result;
    }

    /**
     * Criação explícita: usada apenas quando o administrador confirma que não
     * existe parceiro correspondente no WordPress.
     */
    public function createNew(int $advertiserId): array
    {
        $program = $this->programById($advertiserId, true);
        if (!$program) {
            return ['ok' => false, 'message' => 'Programa Awin não encontrado.', 'advertiser_id' => $advertiserId];
        }
        return $this->syncProgram($program, 0, true);
    }

    private function programById(int $advertiserId, bool $forceRefresh = false): ?array
    {
        if ($advertiserId <= 0) { return null; }
        $list = $this->programs($forceRefresh);
        if (empty($list['ok'])) { return null; }
        foreach ((array) ($list['programs'] ?? []) as $program) {
            if (is_array($program) && (int) ($program['id'] ?? 0) === $advertiserId) {
                return $program;
            }
        }
        if (!$forceRefresh) { return $this->programById($advertiserId, true); }
        return null;
    }

    private function syncProgram(array $program, int $forcedPostId = 0, bool $allowCreate = false): array
    {
        $advertiserId = (int) ($program['id'] ?? 0);
        if (!$advertiserId) {
            return ['ok' => false, 'message' => 'Programa sem Advertiser ID.'];
        }

        $name = sanitize_text_field((string) ($program['name'] ?? ('Awin ' . $advertiserId)));
        $description = wp_kses_post((string) ($program['description'] ?? ''));
        $awinStatus = sanitize_text_field((string) ($program['status'] ?? ''));
        $isActive = strtolower(trim($awinStatus)) === 'active';
        $desiredPostStatus = $isActive ? 'publish' : 'draft';

        if ($forcedPostId > 0) {
            $candidateIds = $this->allRelatedCandidateIds($program, $advertiserId);
            $match = [
                'post_id' => $forcedPostId,
                'method' => 'manual',
                'duplicates' => array_values(array_filter($candidateIds, static function ($id) use ($forcedPostId) { return (int) $id !== $forcedPostId; })),
                'matched_posts' => array_values(array_unique(array_merge([$forcedPostId], $candidateIds))),
                'ambiguous' => false,
            ];
        } else {
            $match = $this->findMatch($program);
        }
        $postId = (int) ($match['post_id'] ?? 0);

        $duplicates = [];
        if ($postId) {
            $duplicates = $this->quarantineDuplicates($postId, $advertiserId, (array) ($match['duplicates'] ?? []));
        }
        $action = 'updated';
        $previousStatus = $postId ? (string) get_post_status($postId) : '';

        if (!$postId) {
            if (!$allowCreate) {
                return [
                    'ok' => false,
                    'message' => 'Criação automática desabilitada para esta operação.',
                    'advertiser_id' => $advertiserId,
                    'name' => $name,
                ];
            }
            $postId = wp_insert_post([
                'post_type' => PartnersProvider::postType(),
                'post_status' => $desiredPostStatus,
                'post_title' => $name,
                'post_content' => $description,
            ]);
            if (is_wp_error($postId) || !$postId) {
                return ['ok' => false, 'message' => 'Não foi possível criar o parceiro no WordPress.', 'advertiser_id' => $advertiserId];
            }
            $action = 'created';
            $match = ['method' => 'new', 'duplicates' => [], 'matched_posts' => [$postId]];
            update_post_meta($postId, 'awin_canonical', '1');
            update_post_meta($postId, 'awin_binding_source', 'explicit_create');
        } else {
            wp_update_post([
                'ID' => $postId,
                'post_status' => $desiredPostStatus,
                'post_title' => $name,
                'post_content' => $description,
            ]);
        }

        $statusAction = 'unchanged';
        if ($isActive && $previousStatus !== 'publish') { $statusAction = 'activated'; }
        if (!$isActive && $previousStatus === 'publish') { $statusAction = 'deactivated'; }
        if ($action === 'created') { $statusAction = $isActive ? 'activated' : 'deactivated'; }

        update_post_meta($postId, 'awin_advertiser_id', $advertiserId);
        update_post_meta($postId, 'awin_relationship_status', 'joined');
        update_post_meta($postId, 'awin_program_status', $awinStatus);
        update_post_meta($postId, 'awin_sync_state', $isActive ? 'active' : 'inactive');
        update_post_meta($postId, 'awin_link_status', sanitize_text_field((string) ($program['linkStatus'] ?? '')));
        update_post_meta($postId, 'awin_currency', sanitize_text_field((string) ($program['currencyCode'] ?? '')));
        update_post_meta($postId, 'awin_primary_sector', sanitize_text_field((string) ($program['primarySector'] ?? '')));
        update_post_meta($postId, 'awin_logo_url', esc_url_raw((string) ($program['logoUrl'] ?? '')));
        update_post_meta($postId, 'awin_click_through_url', esc_url_raw((string) ($program['clickThroughUrl'] ?? '')));
        update_post_meta($postId, 'awin_display_url', esc_url_raw((string) ($program['displayUrl'] ?? '')));
        update_post_meta($postId, 'awin_valid_domains', $program['validDomains'] ?? []);
        update_post_meta($postId, 'awin_last_sync', current_time('mysql'));
        update_post_meta($postId, 'awin_match_method', sanitize_key((string) ($match['method'] ?? '')));

        if ($isActive) {
            delete_post_meta($postId, 'awin_deactivated_at');
            delete_post_meta($postId, 'awin_deactivation_reason');
        } else {
            update_post_meta($postId, 'awin_deactivated_at', current_time('mysql'));
            update_post_meta($postId, 'awin_deactivation_reason', 'program_status:' . sanitize_key($awinStatus));
        }

        if (!get_post_meta($postId, 'nome_fantasia', true)) {
            update_post_meta($postId, 'nome_fantasia', $name);
        }
        if (!get_post_meta($postId, 'descricao_completa', true) && $description !== '') {
            update_post_meta($postId, 'descricao_completa', $description);
        }
        if (!get_post_meta($postId, 'site', true) && !empty($program['displayUrl'])) {
            update_post_meta($postId, 'site', esc_url_raw((string) $program['displayUrl']));
        }
        if (!get_post_meta($postId, 'link_afiliado', true) && !empty($program['clickThroughUrl'])) {
            update_post_meta($postId, 'link_afiliado', esc_url_raw((string) $program['clickThroughUrl']));
        }
        if (get_post_meta($postId, 'awin_cashback_percent', true) === '') {
            update_post_meta($postId, 'awin_cashback_percent', (float) Settings::get('awin_default_cashback_percent', 0));
        }

        return [
            'ok' => true,
            'action' => $action,
            'post_id' => (int) $postId,
            'advertiser_id' => $advertiserId,
            'name' => $name,
            'awin_status' => $awinStatus,
            'wp_status' => $desiredPostStatus,
            'status_action' => $statusAction,
            'match_method' => $match['method'] ?? 'new',
            'matched_posts' => $match['matched_posts'] ?? [$postId],
            'duplicates_quarantined' => $duplicates,
            'canonical_post_id' => (int) $postId,
            'edit_url' => get_edit_post_link($postId, 'raw'),
        ];
    }

    private function deactivateMissing(array $seenAdvertiserIds): int
    {
        global $wpdb;
        $seenAdvertiserIds = array_values(array_unique(array_filter(array_map('absint', $seenAdvertiserIds))));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT pm.post_id, pm.meta_value advertiser_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key=%s AND p.post_status NOT IN ('trash','auto-draft','inherit')",
            'awin_advertiser_id'
        ), ARRAY_A);

        $count = 0;
        foreach ((array) $rows as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $advertiserId = (int) ($row['advertiser_id'] ?? 0);
            if (!$postId || !$this->isPartnerPost($postId) || $advertiserId <= 0 || in_array($advertiserId, $seenAdvertiserIds, true)) { continue; }
            if ($this->deactivatePost($postId, 'missing_from_joined_programs')) { $count++; }
        }
        return $count;
    }

    private function deactivateAdvertiser(int $advertiserId, string $reason): bool
    {
        global $wpdb;
        $postId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key=%s AND pm.meta_value=%s AND p.post_status NOT IN ('trash','auto-draft','inherit') ORDER BY pm.post_id ASC LIMIT 1",
            'awin_advertiser_id', (string) $advertiserId
        ));
        return ($postId && $this->isPartnerPost($postId)) ? $this->deactivatePost($postId, $reason) : false;
    }

    private function deactivatePost(int $postId, string $reason): bool
    {
        if (!$postId) { return false; }
        $current = (string) get_post_status($postId);
        if ($current !== 'draft') {
            wp_update_post(['ID' => $postId, 'post_status' => 'draft']);
        }
        update_post_meta($postId, 'awin_relationship_status', 'not_joined');
        update_post_meta($postId, 'awin_sync_state', 'inactive');
        update_post_meta($postId, 'awin_deactivated_at', current_time('mysql'));
        update_post_meta($postId, 'awin_deactivation_reason', sanitize_key($reason));
        update_post_meta($postId, 'awin_last_sync', current_time('mysql'));
        return $current !== 'draft';
    }

    private function knownPartnerPostTypes(): array
    {
        $types = array_values(array_unique(array_filter([
            PartnersProvider::postType(),
            PartnersProvider::DEFAULT_POST_TYPE,
            'parceiros',
        ])));
        foreach (get_post_types([], 'objects') as $slug => $obj) {
            $label = strtolower(remove_accents((string) ($obj->labels->name ?? '')));
            $singular = strtolower(remove_accents((string) ($obj->labels->singular_name ?? '')));
            if (strpos($label, 'parceir') !== false || strpos($singular, 'parceir') !== false) {
                $types[] = sanitize_key((string) $slug);
            }
        }
        return array_values(array_unique(array_filter($types)));
    }

    private function isPartnerPost(int $postId): bool
    {
        if ($postId <= 0) { return false; }
        $post = get_post($postId);
        if (!$post instanceof \WP_Post) { return false; }
        if (in_array($post->post_type, $this->knownPartnerPostTypes(), true)) { return true; }
        // Fallback para CPTs históricos que não estejam registrados no momento da
        // ação mas possuam os metadados usados pelo catálogo de parceiros.
        return metadata_exists('post', $postId, 'nome_fantasia') || metadata_exists('post', $postId, 'cnpj_do_parceiro');
    }

    private function candidatePostIds(): array
    {
        global $wpdb;
        $postTypes = $this->knownPartnerPostTypes();
        $typeSql = '';
        $params = [];
        if ($postTypes) {
            $placeholders = implode(',', array_fill(0, count($postTypes), '%s'));
            $typeSql = "p.post_type IN ($placeholders)";
            $params = $postTypes;
        }
        $metaKeys = ['nome_fantasia','site','link_afiliado','cnpj_do_parceiro','awin_advertiser_id','awin_duplicate_advertiser_id'];
        $metaPlaceholders = implode(',', array_fill(0, count($metaKeys), '%s'));
        $params = array_merge($params, $metaKeys);
        $whereTypes = $typeSql !== '' ? '(' . $typeSql . " OR pm.meta_key IN ($metaPlaceholders))" : "pm.meta_key IN ($metaPlaceholders)";
        $sql = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=p.ID WHERE p.post_status NOT IN ('trash','auto-draft','inherit') AND $whereTypes ORDER BY p.ID ASC";
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, ...$params)));
    }

    public function candidateDetails(array $program): array
    {
        $advertiserId = (int) ($program['id'] ?? 0);
        $programName = $this->normalizeName((string) ($program['name'] ?? ''));
        $domains = $this->programDomains($program);
        $out = [];
        foreach ($this->candidatePostIds() as $postId) {
            $reasons = [];
            $score = 0;
            $stored = (int) get_post_meta($postId, 'awin_advertiser_id', true);
            $duplicate = (int) get_post_meta($postId, 'awin_duplicate_advertiser_id', true);
            if ($advertiserId && $stored === $advertiserId) { $reasons[]='advertiser_id'; $score += 1000; }
            if ($advertiserId && $duplicate === $advertiserId) { $reasons[]='duplicate_advertiser_id'; $score += 900; }

            foreach ([get_post_meta($postId,'site',true), get_post_meta($postId,'link_afiliado',true), get_post_meta($postId,'awin_display_url',true)] as $url) {
                $host = $this->normalizeHost((string) $url);
                if ($host !== '' && in_array($host, $domains, true)) { $reasons[]='domain'; $score += 700; break; }
            }

            $candidateNames = [(string) get_post_field('post_title',$postId), (string) get_post_meta($postId,'nome_fantasia',true)];
            foreach ($candidateNames as $candidateName) {
                $normalized = $this->normalizeName($candidateName);
                if ($programName !== '' && $normalized === $programName) { $reasons[]='name'; $score += 600; break; }
                if ($programName !== '' && $normalized !== '' && (strpos($normalized,$programName)!==false || strpos($programName,$normalized)!==false)) { $reasons[]='name_similar'; $score += 250; break; }
            }

            if (!$reasons) { continue; }
            $out[] = [
                'post_id' => $postId,
                'title' => (string) get_post_field('post_title',$postId),
                'nome_fantasia' => (string) get_post_meta($postId,'nome_fantasia',true),
                'post_type' => (string) get_post_type($postId),
                'post_status' => (string) get_post_status($postId),
                'site' => (string) get_post_meta($postId,'site',true),
                'advertiser_id' => $stored,
                'canonical' => (string) get_post_meta($postId,'awin_canonical',true) === '1',
                'reasons' => array_values(array_unique($reasons)),
                'score' => $score,
                'edit_url' => get_edit_post_link($postId,'raw'),
            ];
        }
        usort($out, static function($a,$b){ return ($b['score'] <=> $a['score']) ?: ($a['post_id'] <=> $b['post_id']); });
        return $out;
    }

    private function allRelatedCandidateIds(array $program, int $advertiserId): array
    {
        $ids = [];
        foreach ($this->candidateDetails($program) as $candidate) { $ids[] = (int) $candidate['post_id']; }
        global $wpdb;
        foreach (['awin_advertiser_id','awin_duplicate_advertiser_id'] as $key) {
            $found = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value=%s", $key, (string) $advertiserId));
            $ids = array_merge($ids, array_map('intval',(array)$found));
        }
        return array_values(array_unique(array_filter($ids, [$this,'isPartnerPost'])));
    }

    private function findMatch(array $program): array
    {
        $advertiserId = (int) ($program['id'] ?? 0);
        $candidates = $this->candidateDetails($program);
        if (!$candidates) {
            return ['post_id'=>0,'method'=>'new','duplicates'=>[],'matched_posts'=>[],'ambiguous'=>false];
        }

        // Uma decisão manual explícita continua soberana.
        foreach ($candidates as $candidate) {
            if (!empty($candidate['canonical'])
                && (int) $candidate['advertiser_id'] === $advertiserId
                && (string) get_post_meta((int) $candidate['post_id'], 'awin_binding_source', true) === 'manual') {
                $canonical = (int) $candidate['post_id'];
                $strongIds = $this->strongCandidateIds($candidates);
                return [
                    'post_id' => $canonical,
                    'method' => 'manual',
                    'duplicates' => array_values(array_diff($strongIds, [$canonical])),
                    'matched_posts' => $strongIds,
                    'ambiguous' => false,
                ];
            }
        }

        // Prioridade determinística: Advertiser ID > domínio > nome exato.
        // Havendo mais de um candidato na mesma classe, preserva o post mais antigo
        // (menor ID) e coloca os demais em quarentena.
        $priorities = [
            'advertiser_id' => 1,
            'duplicate_advertiser_id' => 2,
            'domain' => 3,
            'name' => 4,
        ];
        $eligible = [];
        foreach ($candidates as $candidate) {
            $best = 999;
            $method = '';
            foreach ((array) ($candidate['reasons'] ?? []) as $reason) {
                if (isset($priorities[$reason]) && $priorities[$reason] < $best) {
                    $best = $priorities[$reason];
                    $method = $reason;
                }
            }
            if ($method !== '') {
                $candidate['_priority'] = $best;
                $candidate['_method'] = $method;
                $eligible[] = $candidate;
            }
        }

        if (!$eligible) {
            // Similaridade fraca de nome não é suficiente para substituir um cadastro.
            // Neste caso o programa Awin vira um novo parceiro automaticamente.
            return ['post_id'=>0,'method'=>'new','duplicates'=>[],'matched_posts'=>[],'ambiguous'=>false];
        }

        usort($eligible, static function ($a, $b) {
            return ($a['_priority'] <=> $b['_priority']) ?: ((int)$a['post_id'] <=> (int)$b['post_id']);
        });
        $winner = $eligible[0];
        $canonical = (int) $winner['post_id'];
        $bestPriority = (int) $winner['_priority'];

        // Quarentena somente candidatos com evidência forte. Isso evita desativar um
        // parceiro diferente por mera semelhança textual.
        $strongIds = $this->strongCandidateIds($candidates);
        return [
            'post_id' => $canonical,
            'method' => (string) $winner['_method'],
            'duplicates' => array_values(array_diff($strongIds, [$canonical])),
            'matched_posts' => $strongIds ?: [$canonical],
            'ambiguous' => false,
        ];
    }

    private function strongCandidateIds(array $candidates): array
    {
        $ids = [];
        foreach ($candidates as $candidate) {
            $reasons = (array) ($candidate['reasons'] ?? []);
            if (array_intersect($reasons, ['advertiser_id','duplicate_advertiser_id','domain','name'])) {
                $ids[] = (int) ($candidate['post_id'] ?? 0);
            }
        }
        sort($ids, SORT_NUMERIC);
        return array_values(array_unique(array_filter($ids)));
    }

    private function quarantineDuplicates(int $canonicalId, int $advertiserId, array $duplicates): array
    {
        $quarantined = [];
        foreach (array_values(array_unique(array_map('absint', $duplicates))) as $duplicateId) {
            if (!$duplicateId || $duplicateId === $canonicalId) { continue; }
            if (!$this->isPartnerPost($duplicateId)) { continue; }

            // Nunca apaga dados. Apenas tira o duplicado da listagem pública e rompe
            // o vínculo Awin para que futuras sincronizações escolham o canônico.
            if (get_post_status($duplicateId) !== 'draft') {
                wp_update_post(['ID' => $duplicateId, 'post_status' => 'draft']);
            }
            delete_post_meta($duplicateId, 'awin_advertiser_id');
            delete_post_meta($duplicateId, 'awin_canonical');
            update_post_meta($duplicateId, 'awin_duplicate_of', $canonicalId);
            update_post_meta($duplicateId, 'awin_duplicate_advertiser_id', $advertiserId);
            update_post_meta($duplicateId, 'awin_sync_state', 'duplicate');
            update_post_meta($duplicateId, 'awin_deactivation_reason', 'duplicate_of_' . $canonicalId);
            update_post_meta($duplicateId, 'awin_deactivated_at', current_time('mysql'));
            $quarantined[] = $duplicateId;
        }

        update_post_meta($canonicalId, 'awin_canonical', '1');
        delete_post_meta($canonicalId, 'awin_duplicate_of');
        delete_post_meta($canonicalId, 'awin_duplicate_advertiser_id');
        return $quarantined;
    }

    private function programDomains(array $program): array
    {
        $domains = [];
        foreach ((array) ($program['validDomains'] ?? []) as $entry) {
            $domain = is_array($entry) ? (string) ($entry['domain'] ?? '') : (string) $entry;
            $domain = $this->normalizeHost($domain);
            if ($domain !== '') { $domains[] = $domain; }
        }
        // displayUrl representa o anunciante. clickThroughUrl aponta para awin1.com e
        // não deve ser usado para correspondência de domínio com o parceiro.
        $host = $this->normalizeHost((string) ($program['displayUrl'] ?? ''));
        if ($host !== '') { $domains[] = $host; }
        return array_values(array_unique($domains));
    }

    private function normalizeHost(string $url): string
    {
        $url = trim(strtolower($url));
        if ($url === '') { return ''; }
        $url = preg_replace('#^\\*\\.#', '', $url);
        if (!preg_match('#^https?://#', $url)) { $url = 'https://' . $url; }
        $host = (string) wp_parse_url($url, PHP_URL_HOST);
        $host = preg_replace('#^www\\.#', '', strtolower($host));
        return rtrim($host, '.');
    }

    private function normalizeName(string $name): string
    {
        $name = remove_accents(strtolower(trim(wp_strip_all_tags($name))));
        $name = preg_replace('/\\b(br|brasil|brazil|loja oficial|oficial)\\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9]+/u', '', $name);
        return (string) $name;
    }

}