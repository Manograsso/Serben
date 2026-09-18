<?php
namespace SerbenConnect\Integrations\Awin;

use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) { exit; }

final class Admin
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_post_serben_awin_test', [$this, 'test']);
        add_action('admin_post_serben_awin_sync_programs', [$this, 'syncPrograms']);
        add_action('wp_ajax_serben_awin_batch_start', [$this, 'batchStart']);
        add_action('wp_ajax_serben_awin_batch_step', [$this, 'batchStep']);
        add_action('admin_post_serben_awin_sync_program', [$this, 'syncProgram']);
        add_action('admin_post_serben_awin_bind_canonical', [$this, 'bindCanonical']);
        add_action('admin_post_serben_awin_create_program', [$this, 'createProgram']);
        add_action('admin_post_serben_awin_sync_offers', [$this, 'syncOffers']);
        add_action('admin_post_serben_awin_sync_transactions', [$this, 'syncTransactions']);
    }

    public function menu(): void
    {
        add_submenu_page('serben-connect', 'Awin', 'Awin', 'manage_options', 'serben-connect-awin', [$this, 'page']);
    }

    private function guard(string $nonce): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer($nonce)) {
            wp_die('Acesso negado.');
        }
    }

    public function test(): void
    {
        $this->guard('serben_awin_test');
        $p = (string) Settings::get('awin_publisher_id');
        $r = (new Client())->get('publishers/' . $p . '/programmes', ['relationship' => 'joined']);
        set_transient('serben_awin_admin_result', ['action' => 'test', 'result' => $r], 180);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-awin&done=1'));
        exit;
    }

    public function syncPrograms(): void
    {
        $this->guard('serben_awin_sync_programs');
        // Fallback sem JavaScript: mantém o comportamento anterior. A interface
        // normal usa AJAX em lotes para não ficar presa ao timeout do gateway.
        $r = (new ProgramSync())->sync();
        set_transient('serben_awin_admin_result', ['action' => 'programs_fallback', 'result' => $r], 180);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-awin&done=1'));
        exit;
    }

    private function ajaxGuard(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Acesso negado.'], 403);
        }
        check_ajax_referer('serben_awin_batch', 'nonce');
    }

    public function batchStart(): void
    {
        $this->ajaxGuard();
        $r = (new ProgramSync())->startBatch();
        if (empty($r['ok'])) { wp_send_json_error($r); }
        wp_send_json_success($r);
    }

    public function batchStep(): void
    {
        $this->ajaxGuard();
        $offset = absint($_POST['offset'] ?? 0);
        $limit = absint($_POST['limit'] ?? 3);
        $r = (new ProgramSync())->syncBatch($offset, $limit);
        if (empty($r['ok'])) { wp_send_json_error($r); }
        wp_send_json_success($r);
    }

    public function syncProgram(): void
    {
        $this->guard('serben_awin_sync_program');
        $advertiserId = absint($_POST['advertiser_id'] ?? 0);
        $r = (new ProgramSync())->syncOne($advertiserId);
        set_transient('serben_awin_admin_result', ['action' => 'program', 'result' => $r], 180);
        $q = sanitize_text_field(wp_unslash($_POST['search_query'] ?? ''));
        $url = admin_url('admin.php?page=serben-connect-awin&done=1');
        if ($q !== '') { $url = add_query_arg('awin_q', rawurlencode($q), $url); }
        wp_safe_redirect($url);
        exit;
    }

    public function bindCanonical(): void
    {
        $this->guard('serben_awin_bind_canonical');
        $advertiserId = absint($_POST['advertiser_id'] ?? 0);
        $postId = absint($_POST['post_id'] ?? 0);
        $r = (new ProgramSync())->bindCanonical($advertiserId, $postId);
        set_transient('serben_awin_admin_result', ['action' => 'bind_canonical', 'result' => $r], 300);
        $q = sanitize_text_field(wp_unslash($_POST['search_query'] ?? ''));
        $url = admin_url('admin.php?page=serben-connect-awin&done=1');
        if ($q !== '') { $url = add_query_arg('awin_q', rawurlencode($q), $url); }
        wp_safe_redirect($url);
        exit;
    }

    public function createProgram(): void
    {
        $this->guard('serben_awin_create_program');
        $advertiserId = absint($_POST['advertiser_id'] ?? 0);
        $r = (new ProgramSync())->createNew($advertiserId);
        set_transient('serben_awin_admin_result', ['action' => 'explicit_create', 'result' => $r], 300);
        $q = sanitize_text_field(wp_unslash($_POST['search_query'] ?? ''));
        $url = admin_url('admin.php?page=serben-connect-awin&done=1');
        if ($q !== '') { $url = add_query_arg('awin_q', rawurlencode($q), $url); }
        wp_safe_redirect($url);
        exit;
    }

    public function syncOffers(): void
    {
        $this->guard('serben_awin_sync_offers');
        $r = (new OfferSync())->sync();
        set_transient('serben_awin_admin_result', ['action' => 'offers', 'result' => $r], 180);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-awin&done=1'));
        exit;
    }

    public function syncTransactions(): void
    {
        $this->guard('serben_awin_sync_transactions');
        $r = (new TransactionSync())->sync();
        set_transient('serben_awin_admin_result', ['action' => 'transactions', 'result' => $r], 180);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-awin&done=1'));
        exit;
    }

    public function page(): void
    {
        $s = Settings::all();
        $last = get_transient('serben_awin_admin_result');
        global $wpdb;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}serben_awin_transactions");
        $credited = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}serben_awin_transactions WHERE serben_status='credited'");

        echo '<div class="wrap"><h1>Awin + Cashback Serben</h1><p>Publisher <strong>' . esc_html($s['awin_publisher_id']) . '</strong>. Transações locais: ' . $count . '; creditadas na Serben: ' . $credited . '.</p>';

        if (is_array($last)) {
            echo '<div class="notice notice-info"><p><strong>Última ação:</strong> ' . esc_html($last['action']) . '</p><pre style="white-space:pre-wrap;max-height:360px;overflow:auto">' . esc_html(print_r($last['result'], true)) . '</pre></div>';
        }

        echo '<p>Use Configurações para informar o token. A sincronização mantém o status do WordPress alinhado à Awin: <strong>Active = publicado</strong>; Hidden, outros estados ou programas que deixarem de aparecer entre os joined = <strong>rascunho/desativado</strong>. Parceiros sem vínculo Awin não são alterados.</p>';

        foreach ([
            ['serben_awin_test', 'Testar conexão Awin'],
            ['serben_awin_sync_offers', 'Sincronizar ofertas'],
            ['serben_awin_sync_transactions', 'Sincronizar transações e cashback'],
        ] as $a) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block;margin:0 10px 10px 0">';
            wp_nonce_field($a[0]);
            echo '<input type="hidden" name="action" value="' . esc_attr($a[0]) . '">';
            submit_button($a[1], 'secondary', 'submit', false);
            echo '</form>';
        }

        $nonce = wp_create_nonce('serben_awin_batch');
        echo '<div style="margin:8px 0 18px"><button type="button" class="button button-primary" id="serben-awin-sync-all-batched">Sincronizar todos os parceiros</button>';
        echo '<div id="serben-awin-batch-status" style="display:none;max-width:760px;margin-top:12px;padding:12px;background:#fff;border:1px solid #ccd0d4">';
        echo '<div><strong id="serben-awin-batch-title">Preparando sincronização…</strong></div>';
        echo '<progress id="serben-awin-batch-progress" value="0" max="100" style="width:100%;height:22px;margin:8px 0"></progress>';
        echo '<div id="serben-awin-batch-detail"></div></div></div>';
        echo '<script>(function(){const b=document.getElementById("serben-awin-sync-all-batched");if(!b)return;const box=document.getElementById("serben-awin-batch-status"),bar=document.getElementById("serben-awin-batch-progress"),title=document.getElementById("serben-awin-batch-title"),detail=document.getElementById("serben-awin-batch-detail");const ajax=' . wp_json_encode(admin_url('admin-ajax.php')) . ',nonce=' . wp_json_encode($nonce) . ';let totals={created:0,updated:0,activated:0,deactivated:0,skipped:0};async function call(action,data){const p=new URLSearchParams(Object.assign({action:action,nonce:nonce},data||{}));const r=await fetch(ajax,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:p.toString()});const j=await r.json();if(!j.success)throw new Error((j.data&&j.data.message)||"Falha na sincronização");return j.data;}async function step(offset,total,limit){const d=await call("serben_awin_batch_step",{offset:String(offset),limit:String(limit)});["created","updated","activated","deactivated","skipped"].forEach(k=>totals[k]+=Number(d[k]||0));const done=Number(d.next_offset||0),pct=total?Math.round((done/total)*100):100;bar.value=pct;title.textContent="Sincronizando parceiros: "+done+" de "+total+" ("+pct+"%)";detail.textContent="Criados: "+totals.created+" | Atualizados: "+totals.updated+" | Ativados: "+totals.activated+" | Desativados: "+totals.deactivated+" | Ignorados: "+totals.skipped;if(d.done){title.textContent="Sincronização concluída: "+total+" parceiros processados";b.disabled=false;b.textContent="Sincronizar todos os parceiros";return;}await new Promise(r=>setTimeout(r,250));return step(Number(d.next_offset||0),total,limit);}b.addEventListener("click",async function(){if(!confirm("Sincronizar todos os parceiros Awin agora? O processo será executado em pequenos lotes para evitar timeout."))return;b.disabled=true;b.textContent="Sincronizando…";box.style.display="block";bar.value=0;totals={created:0,updated:0,activated:0,deactivated:0,skipped:0};try{const s=await call("serben_awin_batch_start",{});const total=Number(s.total||0),limit=Number(s.batch_size||3);title.textContent="Programas encontrados: "+total;detail.textContent="Iniciando lotes de "+limit+"…";if(!total){bar.value=100;title.textContent="Nenhum programa encontrado.";b.disabled=false;return;}await step(0,total,limit);}catch(e){title.textContent="Falha na sincronização";detail.textContent=e.message;b.disabled=false;b.textContent="Tentar novamente";}});})();</script>';

        $this->programSelector();

        echo '<h2>Shortcodes</h2><p><code>[serben_partner_link]</code> — botão universal (Awin com cashback, Awin sem cashback ou link comum). Compatibilidade: <code>[serben_partner_awin_link]</code>. Histórico: <code>[serben_awin_cashback_history]</code> <code>[serben_awin_cashback_pending]</code></p></div>';
    }

    private function programSelector(): void
    {
        $query = sanitize_text_field(wp_unslash($_GET['awin_q'] ?? ''));
        $sync = new ProgramSync();
        $list = $sync->programs(false);

        echo '<hr><h2>Sincronização seletiva de parceiros</h2>';
        echo '<p>Pesquise por nome, Advertiser ID ou domínio. A sincronização funciona como espelho da Awin: parceiros existentes são atualizados e programas sem correspondência são criados automaticamente. A resolução manual continua disponível para corrigir vínculos específicos.</p>';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="margin:12px 0 18px">';
        echo '<input type="hidden" name="page" value="serben-connect-awin">';
        echo '<input type="search" name="awin_q" value="' . esc_attr($query) . '" placeholder="Ex.: Nike, 17652 ou nike.com.br" style="width:360px;max-width:100%"> ';
        submit_button('Buscar programa', 'secondary', 'submit', false);
        if ($query !== '') {
            echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=serben-connect-awin')) . '">Limpar busca</a>';
        }
        echo '</form>';

        if (empty($list['ok'])) {
            echo '<div class="notice notice-error inline"><p>' . esc_html($list['message'] ?? 'Não foi possível carregar os programas Awin.') . '</p></div>';
            return;
        }

        $programs = $this->filterPrograms((array) ($list['programs'] ?? []), $query);
        if ($query === '') {
            echo '<p><em>Digite uma busca acima para listar programas individualmente. Isso evita carregar uma tabela muito grande.</em></p>';
            return;
        }
        if (!$programs) {
            echo '<p>Nenhum programa encontrado para <strong>' . esc_html($query) . '</strong>.</p>';
            return;
        }

        echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Programa</th><th>Advertiser ID</th><th>Status</th><th>Moeda</th><th>Domínio/Site</th><th>Ação</th></tr></thead><tbody>';
        foreach (array_slice($programs, 0, 50) as $program) {
            $id = absint($program['id'] ?? 0);
            $name = (string) ($program['name'] ?? '');
            $status = (string) ($program['status'] ?? '');
            $currency = (string) ($program['currencyCode'] ?? '');
            $display = (string) ($program['displayUrl'] ?? '');
            echo '<tr><td><strong>' . esc_html($name) . '</strong></td><td>' . esc_html((string) $id) . '</td><td>' . esc_html($status) . '</td><td>' . esc_html($currency) . '</td><td>';
            if ($display !== '') { echo '<a href="' . esc_url($display) . '" target="_blank" rel="noopener">' . esc_html($display) . '</a>'; }
            echo '</td><td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:6px">';
            wp_nonce_field('serben_awin_sync_program');
            echo '<input type="hidden" name="action" value="serben_awin_sync_program">';
            echo '<input type="hidden" name="advertiser_id" value="' . esc_attr((string) $id) . '">';
            echo '<input type="hidden" name="search_query" value="' . esc_attr($query) . '">';
            submit_button('Sincronizar este parceiro', 'primary small', 'submit', false);
            echo '</form>';
            $this->mappingResolver($id, $query);
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        if (count($programs) > 50) {
            echo '<p><em>Foram encontrados ' . esc_html((string) count($programs)) . ' programas; exibindo os primeiros 50. Refine a busca.</em></p>';
        }
    }

    private function mappingResolver(int $advertiserId, string $query): void
    {
        $inspection = (new ProgramSync())->inspectOne($advertiserId);
        if (empty($inspection['ok'])) { return; }
        $candidates = (array) ($inspection['candidates'] ?? []);

        echo '<details style="margin-top:6px"><summary>Resolver correspondência</summary><div style="min-width:440px;max-width:700px;padding:8px 0">';
        if ($candidates) {
            echo '<p><strong>Possíveis parceiros encontrados:</strong></p><table class="widefat striped"><thead><tr><th>Post</th><th>Nome</th><th>Status</th><th>Motivo</th><th></th></tr></thead><tbody>';
            foreach (array_slice($candidates,0,20) as $candidate) {
                $pid=(int)($candidate['post_id']??0);
                $label=(string)($candidate['nome_fantasia']??''); if($label===''){$label=(string)($candidate['title']??'');}
                echo '<tr><td>#'.esc_html((string)$pid).'</td><td>'.esc_html($label).'<br><small>'.esc_html((string)($candidate['post_type']??'')).'</small></td><td>'.esc_html((string)($candidate['post_status']??'')).'</td><td>'.esc_html(implode(', ',(array)($candidate['reasons']??[]))).'</td><td>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                wp_nonce_field('serben_awin_bind_canonical');
                echo '<input type="hidden" name="action" value="serben_awin_bind_canonical"><input type="hidden" name="advertiser_id" value="'.esc_attr((string)$advertiserId).'"><input type="hidden" name="post_id" value="'.esc_attr((string)$pid).'"><input type="hidden" name="search_query" value="'.esc_attr($query).'">';
                submit_button('Definir como principal','secondary small','submit',false);
                echo '</form></td></tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>Nenhum candidato automático foi localizado.</p>';
        }

        echo '<p><strong>Informar Post ID manualmente</strong></p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('serben_awin_bind_canonical');
        echo '<input type="hidden" name="action" value="serben_awin_bind_canonical"><input type="hidden" name="advertiser_id" value="'.esc_attr((string)$advertiserId).'"><input type="hidden" name="search_query" value="'.esc_attr($query).'">';
        echo '<input type="number" min="1" name="post_id" placeholder="Ex.: 2158" required> ';
        submit_button('Vincular este Post ID','secondary','submit',false);
        echo '</form>';

        echo "<hr><p><strong>Criação manual opcional:</strong></p><form method=\"post\" action=\"" . esc_url(admin_url('admin-post.php')) . "\" onsubmit=\"return confirm('Criar um novo parceiro para este programa Awin? Use somente se não existir cadastro correspondente.')\">";
        wp_nonce_field('serben_awin_create_program');
        echo '<input type="hidden" name="action" value="serben_awin_create_program"><input type="hidden" name="advertiser_id" value="'.esc_attr((string)$advertiserId).'"><input type="hidden" name="search_query" value="'.esc_attr($query).'">';
        submit_button('Criar novo parceiro','delete small','submit',false);
        echo '</form></div></details>';
    }

    private function filterPrograms(array $programs, string $query): array
    {
        $needle = strtolower(trim(remove_accents($query)));
        if ($needle === '') { return []; }

        return array_values(array_filter($programs, static function ($program) use ($needle) {
            if (!is_array($program)) { return false; }
            $parts = [
                (string) ($program['id'] ?? ''),
                (string) ($program['name'] ?? ''),
                (string) ($program['displayUrl'] ?? ''),
                (string) ($program['primarySector'] ?? ''),
            ];
            foreach ((array) ($program['validDomains'] ?? []) as $domain) {
                $parts[] = is_array($domain) ? (string) ($domain['domain'] ?? '') : (string) $domain;
            }
            $haystack = strtolower(remove_accents(implode(' ', $parts)));
            return strpos($haystack, $needle) !== false;
        }));
    }
}
