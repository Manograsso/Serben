<?php
namespace SerbenConnect\Integrations\WooCommerce;

use SerbenConnect\Services\PlanosService;
use SerbenConnect\Support\Logger;
use SerbenConnect\Support\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class PlanProductSync
{
    public const META_PLAN_ID = '_serben_plan_id';
    public const META_SYNC_ENABLED = '_serben_sync_enabled';
    public const META_DEPENDENT_PRICE = '_serben_dependent_price_cents';
    public const META_RENEWAL = '_serben_auto_renewal';
    public const META_RESALE_LINK = '_serben_resale_link';
    public const META_DUE_DAYS = '_serben_due_days';
    public const META_PRODUCTS_JSON = '_serben_products_json';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 30);
        add_action('admin_post_serben_import_plans_to_woocommerce', [$this, 'handleImport']);
        add_action('admin_post_serben_export_product_to_serben', [$this, 'handleExport']);
        add_action('admin_post_serben_unlink_product_plan', [$this, 'handleUnlink']);

        if ($this->woocommerceAvailable()) {
            add_action('woocommerce_product_options_general_product_data', [$this, 'productFields']);
            add_action('woocommerce_admin_process_product_object', [$this, 'saveProductFields']);
            add_action('woocommerce_order_status_completed', [$this, 'orderCompleted'], 10, 1);
        }
    }

    public function menu(): void
    {
        add_submenu_page(
            'serben-connect',
            'Planos e WooCommerce',
            'Planos e WooCommerce',
            'manage_woocommerce',
            'serben-connect-woocommerce-plans',
            [$this, 'page']
        );
    }

    public function productFields(): void
    {
        echo '<div class="options_group">';
        woocommerce_wp_checkbox([
            'id' => self::META_SYNC_ENABLED,
            'label' => 'Plano Serben',
            'description' => 'Marque para permitir que este produto seja publicado como plano na API Serben.',
        ]);
        woocommerce_wp_text_input([
            'id' => self::META_PLAN_ID,
            'label' => 'ID do plano Serben',
            'description' => 'Preenchido automaticamente após importação ou publicação.',
            'desc_tip' => true,
            'type' => 'text',
        ]);
        woocommerce_wp_text_input([
            'id' => self::META_DEPENDENT_PRICE,
            'label' => 'Valor do dependente (centavos)',
            'description' => 'Ex.: 990 para R$ 9,90.',
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => ['min' => '0', 'step' => '1'],
        ]);
        woocommerce_wp_checkbox([
            'id' => self::META_RENEWAL,
            'label' => 'Renovação automática',
        ]);
        woocommerce_wp_checkbox([
            'id' => self::META_RESALE_LINK,
            'label' => 'Permite revenda por link',
        ]);
        woocommerce_wp_text_input([
            'id' => self::META_DUE_DAYS,
            'label' => 'Dias para vencimento',
            'type' => 'number',
            'custom_attributes' => ['min' => '0', 'step' => '1'],
        ]);
        woocommerce_wp_textarea_input([
            'id' => self::META_PRODUCTS_JSON,
            'label' => 'Produtos/benefícios do plano (JSON)',
            'description' => 'Ex.: [{"id":34,"quantidade_mensal":0}]',
            'desc_tip' => true,
        ]);
        echo '</div>';
    }

    public function saveProductFields($product): void
    {
        if (!is_object($product) || !method_exists($product, 'update_meta_data')) {
            return;
        }

        $product->update_meta_data(self::META_SYNC_ENABLED, isset($_POST[self::META_SYNC_ENABLED]) ? 'yes' : 'no');
        $product->update_meta_data(self::META_PLAN_ID, sanitize_text_field(wp_unslash($_POST[self::META_PLAN_ID] ?? '')));
        $product->update_meta_data(self::META_DEPENDENT_PRICE, max(0, (int)($_POST[self::META_DEPENDENT_PRICE] ?? 0)));
        $product->update_meta_data(self::META_RENEWAL, isset($_POST[self::META_RENEWAL]) ? 'yes' : 'no');
        $product->update_meta_data(self::META_RESALE_LINK, isset($_POST[self::META_RESALE_LINK]) ? 'yes' : 'no');
        $product->update_meta_data(self::META_DUE_DAYS, max(0, (int)($_POST[self::META_DUE_DAYS] ?? 7)));

        $json = trim((string)wp_unslash($_POST[self::META_PRODUCTS_JSON] ?? ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $product->update_meta_data(self::META_PRODUCTS_JSON, wp_json_encode($decoded));
            }
        } else {
            $product->delete_meta_data(self::META_PRODUCTS_JSON);
        }
    }

    public function handleImport(): void
    {
        $this->authorize('serben_import_plans_to_woocommerce');

        if (!$this->woocommerceAvailable()) {
            $this->redirect(['serben_sync_error' => 'woocommerce_missing']);
        }

        $result = $this->importPlans();
        $this->redirect([
            'serben_imported' => (int)$result['created'],
            'serben_updated' => (int)$result['updated'],
            'serben_skipped' => (int)$result['skipped'],
        ]);
    }

    public function handleExport(): void
    {
        $this->authorize('serben_export_product_to_serben');
        $productId = absint($_POST['product_id'] ?? 0);
        $result = $this->exportProduct($productId);

        $args = $result['ok']
            ? ['serben_exported' => $productId, 'serben_plan_id' => (string)($result['plan_id'] ?? '')]
            : ['serben_sync_error' => rawurlencode((string)($result['message'] ?? 'Falha ao publicar plano.'))];
        $this->redirect($args);
    }

    public function handleUnlink(): void
    {
        $this->authorize('serben_unlink_product_plan');
        $productId = absint($_POST['product_id'] ?? 0);
        if ($productId > 0) {
            delete_post_meta($productId, self::META_PLAN_ID);
        }
        $this->redirect(['serben_unlinked' => $productId]);
    }

    public function importPlans(): array
    {
        $response = (new PlanosService())->listar();
        $plans = $this->normalizePlans($response['body'] ?? []);
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        if (empty($response['ok']) || !$plans) {
            Logger::add('error', 'SYNC', 'woocommerce/import-plans', (int)($response['code'] ?? 0), 'Nenhum plano importado', ['response' => $response]);
            return $stats;
        }

        foreach ($plans as $plan) {
            $planId = $this->planId($plan);
            if ($planId === '') {
                $stats['skipped']++;
                continue;
            }

            $existing = get_posts([
                'post_type' => 'product',
                'post_status' => ['publish', 'draft', 'private', 'pending'],
                'fields' => 'ids',
                'posts_per_page' => 1,
                'meta_key' => self::META_PLAN_ID,
                'meta_value' => $planId,
            ]);

            $product = !empty($existing) ? wc_get_product((int)$existing[0]) : new \WC_Product_Simple();
            if (!$product) {
                $stats['skipped']++;
                continue;
            }

            $isNew = !$product->get_id();
            $name = (string)($plan['nomePlano'] ?? $plan['nome_plano'] ?? $plan['nome'] ?? ('Plano ' . $planId));
            $description = (string)($plan['descricao_plano'] ?? $plan['descricao'] ?? '');
            $priceCents = (int)($plan['valor_por_titular_em_centavos'] ?? $plan['valor'] ?? 0);

            $product->set_name($name);
            $product->set_description($description);
            $product->set_regular_price($this->decimalPrice($priceCents));
            $product->set_virtual(true);
            if ($isNew) {
                $product->set_status('draft');
            }
            $product->update_meta_data(self::META_PLAN_ID, $planId);
            $product->update_meta_data(self::META_SYNC_ENABLED, 'yes');
            $product->update_meta_data(self::META_DEPENDENT_PRICE, (int)($plan['valor_dependente_em_centavos'] ?? 0));
            $product->update_meta_data(self::META_RENEWAL, !empty($plan['renovacao_automatica_plano']) ? 'yes' : 'no');
            $product->update_meta_data(self::META_RESALE_LINK, !empty($plan['permite_revenda_por_link']) ? 'yes' : 'no');
            $product->update_meta_data(self::META_DUE_DAYS, (int)($plan['diasVencimentoParcela'] ?? 7));
            if (isset($plan['produtos']) && is_array($plan['produtos'])) {
                $product->update_meta_data(self::META_PRODUCTS_JSON, wp_json_encode($plan['produtos']));
            }
            $product->save();

            $stats[$isNew ? 'created' : 'updated']++;
        }

        Logger::add('info', 'SYNC', 'woocommerce/import-plans', 200, 'Planos sincronizados para WooCommerce', $stats);
        return $stats;
    }

    public function exportProduct(int $productId): array
    {
        if (!$this->woocommerceAvailable()) {
            return ['ok' => false, 'message' => 'WooCommerce não está ativo.'];
        }

        $product = wc_get_product($productId);
        if (!$product) {
            return ['ok' => false, 'message' => 'Produto não encontrado.'];
        }

        $products = [];
        $productsJson = (string)$product->get_meta(self::META_PRODUCTS_JSON, true);
        if ($productsJson !== '') {
            $decoded = json_decode($productsJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $products = $decoded;
            }
        }

        $payload = [
            'idLoja' => (int)Settings::get('id_loja'),
            'nomePlano' => $product->get_name(),
            'valor_por_titular_em_centavos' => $this->priceToCents((string)$product->get_regular_price()),
            'valor_dependente_em_centavos' => (int)$product->get_meta(self::META_DEPENDENT_PRICE, true),
            'descricao_plano' => wp_strip_all_tags($product->get_description()),
            'produtos' => $products,
            'pemitir_portador_gerar' => true,
            'renovacao_automatica_plano' => $product->get_meta(self::META_RENEWAL, true) === 'yes',
            'permite_revenda_por_link' => $product->get_meta(self::META_RESALE_LINK, true) !== 'no',
            'diasVencimentoParcela' => max(0, (int)$product->get_meta(self::META_DUE_DAYS, true)),
            'cobrar_adesao' => 0,
            'adesao_com_parcela' => 0,
            'valorSugeridoAdesao' => 0,
            'diasVencimentoAdesao' => 0,
            'restricaoFormaPgto' => [],
        ];

        $response = (new PlanosService())->cadastrar($payload);
        if (empty($response['ok'])) {
            return ['ok' => false, 'message' => 'API retornou HTTP ' . (int)($response['code'] ?? 0), 'response' => $response];
        }

        $planId = $this->extractCreatedPlanId($response['body'] ?? []);
        if ($planId !== '') {
            $product->update_meta_data(self::META_PLAN_ID, $planId);
            $product->update_meta_data(self::META_SYNC_ENABLED, 'yes');
            $product->save();
        }

        Logger::add('info', 'SYNC', 'woocommerce/export-product', (int)($response['code'] ?? 200), 'Produto publicado como plano Serben', [
            'product_id' => $productId,
            'plan_id' => $planId,
            'payload' => $payload,
        ]);

        return ['ok' => true, 'plan_id' => $planId, 'response' => $response];
    }

    public function orderCompleted(int $orderId): void
    {
        $order = wc_get_order($orderId);
        if (!$order) {
            return;
        }

        $planItems = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            $planId = (string)$product->get_meta(self::META_PLAN_ID, true);
            if ($planId !== '') {
                $planItems[] = ['product_id' => $product->get_id(), 'plan_id' => $planId, 'quantity' => $item->get_quantity()];
            }
        }

        if (!$planItems) {
            return;
        }

        update_post_meta($orderId, '_serben_contract_sync_status', 'pending_endpoint');
        update_post_meta($orderId, '_serben_plan_items', $planItems);
        Logger::add('info', 'EVENT', 'woocommerce/order-completed', 202, 'Pedido contém plano Serben; ativação aguardando endpoint de contratação', [
            'order_id' => $orderId,
            'items' => $planItems,
        ]);
    }

    public function page(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Acesso negado.');
        }

        $available = $this->woocommerceAvailable();
        $linked = $available ? get_posts([
            'post_type' => 'product',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => 100,
            'meta_query' => [[
                'key' => self::META_PLAN_ID,
                'compare' => 'EXISTS',
            ]],
        ]) : [];
        ?>
        <div class="wrap">
            <h1>Planos Serben e WooCommerce</h1>
            <?php if (!$available): ?>
                <div class="notice notice-error"><p>WooCommerce não está ativo. Ative-o para usar a sincronização de planos.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['serben_imported'])): ?>
                <div class="notice notice-success"><p>Sincronização concluída: <?php echo esc_html((string)absint($_GET['serben_imported'])); ?> criado(s), <?php echo esc_html((string)absint($_GET['serben_updated'] ?? 0)); ?> atualizado(s), <?php echo esc_html((string)absint($_GET['serben_skipped'] ?? 0)); ?> ignorado(s).</p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['serben_exported'])): ?>
                <div class="notice notice-success"><p>Produto publicado na API Serben. ID retornado: <code><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['serben_plan_id'] ?? 'não informado'))); ?></code></p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['serben_sync_error'])): ?>
                <div class="notice notice-error"><p><?php echo esc_html(sanitize_text_field(wp_unslash($_GET['serben_sync_error']))); ?></p></div>
            <?php endif; ?>

            <h2>Importar planos da API</h2>
            <p>Cria produtos simples e virtuais em rascunho. Produtos já vinculados pelo ID do plano são atualizados.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('serben_import_plans_to_woocommerce'); ?>
                <input type="hidden" name="action" value="serben_import_plans_to_woocommerce">
                <?php submit_button('Importar/atualizar planos', 'primary', 'submit', false, !$available ? ['disabled' => 'disabled'] : []); ?>
            </form>

            <h2>Publicar produto como plano Serben</h2>
            <?php if ($available): ?>
                <p>Configure o produto na aba <strong>Geral</strong>, marque “Plano Serben” e use o botão abaixo. O envio é manual para evitar planos duplicados.</p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;align-items:center;">
                    <?php wp_nonce_field('serben_export_product_to_serben'); ?>
                    <input type="hidden" name="action" value="serben_export_product_to_serben">
                    <select name="product_id" required>
                        <option value="">Selecione um produto</option>
                        <?php foreach (get_posts(['post_type' => 'product', 'post_status' => ['publish','draft','private','pending'], 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC']) as $productPost): ?>
                            <option value="<?php echo esc_attr((string)$productPost->ID); ?>"><?php echo esc_html($productPost->post_title . ' (#' . $productPost->ID . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php submit_button('Publicar na API', 'secondary', 'submit', false); ?>
                </form>
            <?php endif; ?>

            <h2>Produtos vinculados</h2>
            <table class="widefat striped">
                <thead><tr><th>Produto</th><th>Status</th><th>ID plano</th><th>Preço</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (!$linked): ?>
                    <tr><td colspan="5">Nenhum produto vinculado.</td></tr>
                <?php else: foreach ($linked as $post): $product = wc_get_product($post->ID); if (!$product) continue; ?>
                    <tr>
                        <td><a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>"><strong><?php echo esc_html($product->get_name()); ?></strong></a></td>
                        <td><?php echo esc_html($product->get_status()); ?></td>
                        <td><code><?php echo esc_html((string)$product->get_meta(self::META_PLAN_ID, true)); ?></code></td>
                        <td><?php echo wp_kses_post($product->get_price_html()); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('serben_unlink_product_plan'); ?>
                                <input type="hidden" name="action" value="serben_unlink_product_plan">
                                <input type="hidden" name="product_id" value="<?php echo esc_attr((string)$post->ID); ?>">
                                <button class="button button-small">Desvincular</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>

            <h2>Ativação após pagamento</h2>
            <p>Pedidos concluídos que contêm produtos vinculados são marcados como <code>pending_endpoint</code>. A contratação automática será ativada quando a rota de adesão/contrato for confirmada.</p>
        </div>
        <?php
    }

    private function normalizePlans($body): array
    {
        if (!is_array($body)) {
            return [];
        }
        foreach (['data', 'registros', 'planos', 'items'] as $key) {
            if (isset($body[$key])) {
                return $this->normalizePlans($body[$key]);
            }
        }
        if ($this->isList($body)) {
            return array_values(array_filter($body, 'is_array'));
        }
        return $this->planId($body) !== '' ? [$body] : [];
    }

    private function planId(array $plan): string
    {
        foreach (['id_plano', 'idPlano', 'id', 'codigoPlano', 'codigo_plano'] as $key) {
            if (isset($plan[$key]) && (string)$plan[$key] !== '') {
                return sanitize_text_field((string)$plan[$key]);
            }
        }
        return '';
    }

    private function extractCreatedPlanId($body): string
    {
        if (!is_array($body)) {
            return '';
        }
        $direct = $this->planId($body);
        if ($direct !== '') {
            return $direct;
        }
        foreach (['data', 'plano', 'registro', 'result'] as $key) {
            if (isset($body[$key]) && is_array($body[$key])) {
                $found = $this->extractCreatedPlanId($body[$key]);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    private function decimalPrice(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function priceToCents(string $price): int
    {
        return (int)round((float)$price * 100);
    }

    private function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }

    private function woocommerceAvailable(): bool
    {
        return class_exists('WooCommerce') && class_exists('WC_Product_Simple') && function_exists('wc_get_product');
    }

    private function authorize(string $action): void
    {
        if (!current_user_can('manage_woocommerce') || !check_admin_referer($action)) {
            wp_die('Acesso negado.');
        }
    }

    private function redirect(array $args): void
    {
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php?page=serben-connect-woocommerce-plans')));
        exit;
    }
}
