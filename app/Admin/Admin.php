<?php
namespace SerbenConnect\Admin;

use SerbenConnect\Components\ComponentRegistry;
use SerbenConnect\Services\ClientesService;
use SerbenConnect\Registration\FieldGlossary;
use SerbenConnect\Dependents\FieldGlossary as DependentFieldGlossary;
use SerbenConnect\Support\Logger;
use SerbenConnect\Support\Settings;
use SerbenConnect\Core\EnvironmentInspector;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_serben_save_settings', [$this, 'saveSettings']);
        add_action('admin_post_serben_test_connection', [$this, 'testConnection']);
        add_action('admin_post_serben_clear_logs', [$this, 'clearLogs']);
    }

    public function menu(): void
    {
        add_menu_page('Serben Connect', 'Serben Connect', 'manage_options', 'serben-connect', [$this, 'settingsPage'], 'dashicons-rest-api', 56);
        add_submenu_page('serben-connect', 'Configurações', 'Configurações', 'manage_options', 'serben-connect', [$this, 'settingsPage']);
        add_submenu_page('serben-connect', 'Logs', 'Logs', 'manage_options', 'serben-connect-logs', [$this, 'logsPage']);
        add_submenu_page('serben-connect', 'Componentes', 'Componentes', 'manage_options', 'serben-connect-components', [$this, 'componentsPage']);
        add_submenu_page('serben-connect', 'Ambiente', 'Ambiente', 'manage_options', 'serben-connect-environment', [$this, 'environmentPage']);
        add_submenu_page('serben-connect', 'Campos de cadastro', 'Campos de cadastro', 'manage_options', 'serben-connect-registration-fields', [$this, 'registrationFieldsPage']);
        add_submenu_page('serben-connect', 'Campos de dependentes', 'Campos de dependentes', 'manage_options', 'serben-connect-dependent-fields', [$this, 'dependentFieldsPage']);
    }

    public function saveSettings(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('serben_save_settings')) {
            wp_die('Acesso negado.');
        }
        Settings::update($_POST['serben'] ?? []);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect&updated=1'));
        exit;
    }

    public function testConnection(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('serben_test_connection')) {
            wp_die('Acesso negado.');
        }
        $cpf = Settings::get('test_cpf');
        $clientes = new ClientesService();
        $result = $clientes->buscarPorDocumento($cpf);
        $saldo = $clientes->buscarSaldoPorDocumento($cpf);
        set_transient('serben_connect_last_test', ['cliente' => $result, 'saldo' => $saldo], 120);
        wp_safe_redirect(admin_url('admin.php?page=serben-connect&tested=1'));
        exit;
    }

    public function clearLogs(): void
    {
        if (!current_user_can('manage_options') || !check_admin_referer('serben_clear_logs')) {
            wp_die('Acesso negado.');
        }
        Logger::clear();
        wp_safe_redirect(admin_url('admin.php?page=serben-connect-logs&cleared=1'));
        exit;
    }

    public function settingsPage(): void
    {
        $s = Settings::all();
        $last = get_transient('serben_connect_last_test');
        $clienteTest = is_array($last) && isset($last['cliente']) ? $last['cliente'] : $last;
        $saldoTest = is_array($last) && isset($last['saldo']) ? $last['saldo'] : null;
        $partnerPostTypes = [];
        foreach (get_post_types(['show_ui' => true], 'objects') as $postTypeName => $postTypeObject) {
            if (in_array($postTypeName, ['post', 'page', 'attachment'], true)) { continue; }
            $counts = wp_count_posts($postTypeName);
            $published = $counts && isset($counts->publish) ? (int) $counts->publish : 0;
            $label = isset($postTypeObject->labels->singular_name) ? (string) $postTypeObject->labels->singular_name : (string) $postTypeObject->label;
            if (stripos($postTypeName, 'parceir') !== false || stripos($label, 'parceir') !== false || $postTypeName === ($s['partners_post_type'] ?? '')) {
                $partnerPostTypes[$postTypeName] = [
                    'label' => (string) ($postTypeObject->label ?? $postTypeName),
                    'published' => $published,
                ];
            }
        }
        if (!isset($partnerPostTypes['serben_parceiro']) && post_type_exists('serben_parceiro')) {
            $counts = wp_count_posts('serben_parceiro');
            $partnerPostTypes['serben_parceiro'] = ['label' => 'serben_parceiro', 'published' => $counts && isset($counts->publish) ? (int) $counts->publish : 0];
        }
        if (!isset($partnerPostTypes['parceiros']) && post_type_exists('parceiros')) {
            $counts = wp_count_posts('parceiros');
            $partnerPostTypes['parceiros'] = ['label' => 'parceiros', 'published' => $counts && isset($counts->publish) ? (int) $counts->publish : 0];
        }

        $selectedPartnerPostType = sanitize_key((string) ($s['partners_post_type'] ?? 'serben_parceiro'));
        $partnerTaxonomies = [];
        foreach ((array) get_object_taxonomies($selectedPartnerPostType, 'objects') as $taxonomyName => $taxonomyObject) {
            $partnerTaxonomies[$taxonomyName] = [
                'label' => (string) ($taxonomyObject->label ?? $taxonomyName),
                'terms' => (int) wp_count_terms(['taxonomy' => $taxonomyName, 'hide_empty' => false]),
            ];
        }
        ?>
        <div class="wrap">
            <h1>Serben Connect</h1>
            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success"><p>Configurações salvas.</p></div><?php endif; ?>
            <?php if (!empty($_GET['tested']) && is_array($clienteTest)): ?>
                <div class="notice <?php echo !empty($clienteTest['ok']) ? 'notice-success' : 'notice-error'; ?>">
                    <p><strong>Teste cliente:</strong> HTTP <?php echo esc_html((string)($clienteTest['code'] ?? '0')); ?> — <?php echo !empty($clienteTest['ok']) ? 'conectado com sucesso.' : 'falha na chamada.'; ?></p>
                    <p><strong>URL:</strong> <code><?php echo esc_html($clienteTest['url'] ?? ''); ?></code></p>
                    <?php if (is_array($saldoTest)): ?><p><strong>Teste saldo:</strong> HTTP <?php echo esc_html((string)($saldoTest['code'] ?? '0')); ?> — <code><?php echo esc_html($saldoTest['url'] ?? ''); ?></code></p><?php endif; ?>
                    <details><summary>Resposta da API</summary><pre style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #ccd0d4;max-height:350px;overflow:auto;"><?php echo esc_html(print_r($last, true)); ?></pre></details>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('serben_save_settings'); ?>
                <input type="hidden" name="action" value="serben_save_settings">
                <table class="form-table" role="presentation">
                    <tr><th>URL Base</th><td><input class="regular-text" name="serben[base_url]" value="<?php echo esc_attr($s['base_url']); ?>"><p class="description">Ex.: https://serben.conectar.site</p></td></tr>
                    <tr><th>x-api-key</th><td><input class="regular-text" name="serben[api_key]" value="<?php echo esc_attr($s['api_key']); ?>"></td></tr>
                    <tr><th>IDENTIFIER</th><td><input class="regular-text" name="serben[identifier]" value="<?php echo esc_attr($s['identifier']); ?>"></td></tr>
                    <tr><th>ID Loja</th><td><input class="regular-text" name="serben[id_loja]" value="<?php echo esc_attr($s['id_loja']); ?>"><p class="description">Usado em consultas de saldo/pontos quando exigido pela API.</p></td></tr>
                    <tr><th>CNPJ Empresa</th><td><input class="regular-text" name="serben[cnpj_empresa]" value="<?php echo esc_attr($s['cnpj_empresa']); ?>"></td></tr>
                    <tr><th>CNPJ do Credenciador</th><td><input class="regular-text" name="serben[cnpj_credenciador]" value="<?php echo esc_attr($s['cnpj_credenciador'] ?? ''); ?>"><p class="description">Usado para localizar lojas no login de parceiros.</p></td></tr>
                    <tr><th>Código padrão</th><td><input class="regular-text" name="serben[codigo]" value="<?php echo esc_attr($s['codigo']); ?>"></td></tr>
                    <tr><th>CPF de teste</th><td><input class="regular-text" name="serben[test_cpf]" value="<?php echo esc_attr($s['test_cpf']); ?>"></td></tr>
                    <tr><th>URL da página de cadastro</th><td><input class="regular-text" name="serben[register_page_url]" value="<?php echo esc_attr($s['register_page_url']); ?>"><p class="description">Página que contém <code>[serben_register]</code>. Será usada quando o CPF não existir no app.</p></td></tr>
                    <tr><th>Post Type de Parceiros</th><td><select name="serben[partners_post_type]">
                        <?php foreach ($partnerPostTypes as $postTypeName => $postTypeInfo): ?>
                            <option value="<?php echo esc_attr($postTypeName); ?>" <?php selected($s['partners_post_type'] ?? 'serben_parceiro', $postTypeName); ?>><?php echo esc_html(($postTypeInfo['label'] ?? $postTypeName) . ' — ' . $postTypeName . ' (' . (int) ($postTypeInfo['published'] ?? 0) . ' publicados)'); ?></option>
                        <?php endforeach; ?>
                    </select><p class="description">Escolha o slug técnico que contém os parceiros. O plugin detectou os post types compatíveis e mostra a quantidade publicada.</p></td></tr>
                    <tr><th>Taxonomia de Categoria</th><td><select name="serben[partners_category_taxonomy]">
                        <option value="">— Não usar —</option>
                        <?php foreach ($partnerTaxonomies as $taxonomyName => $taxonomyInfo): ?>
                            <option value="<?php echo esc_attr($taxonomyName); ?>" <?php selected($s['partners_category_taxonomy'] ?? 'serben_categoria', $taxonomyName); ?>><?php echo esc_html(($taxonomyInfo['label'] ?? $taxonomyName) . ' — ' . $taxonomyName . ' (' . (int) ($taxonomyInfo['terms'] ?? 0) . ' termos)'); ?></option>
                        <?php endforeach; ?>
                    </select><p class="description">No seu site, a categoria correta é <code>serben_categoria</code>.</p></td></tr>
                    <tr><th>Taxonomia de Localidade</th><td><select name="serben[partners_locality_taxonomy]">
                        <option value="">— Não usar —</option>
                        <?php foreach ($partnerTaxonomies as $taxonomyName => $taxonomyInfo): ?>
                            <option value="<?php echo esc_attr($taxonomyName); ?>" <?php selected($s['partners_locality_taxonomy'] ?? 'localidade', $taxonomyName); ?>><?php echo esc_html(($taxonomyInfo['label'] ?? $taxonomyName) . ' — ' . $taxonomyName . ' (' . (int) ($taxonomyInfo['terms'] ?? 0) . ' termos)'); ?></option>
                        <?php endforeach; ?>
                    </select></td></tr>
                    <tr><th>Taxonomia de Tipo de Benefício</th><td><select name="serben[partners_benefit_taxonomy]">
                        <option value="">— Não usar —</option>
                        <?php foreach ($partnerTaxonomies as $taxonomyName => $taxonomyInfo): ?>
                            <option value="<?php echo esc_attr($taxonomyName); ?>" <?php selected($s['partners_benefit_taxonomy'] ?? 'tipo-de-beneficio', $taxonomyName); ?>><?php echo esc_html(($taxonomyInfo['label'] ?? $taxonomyName) . ' — ' . $taxonomyName . ' (' . (int) ($taxonomyInfo['terms'] ?? 0) . ' termos)'); ?></option>
                        <?php endforeach; ?>
                    </select></td></tr>
                    <tr><th>Cache do associado</th><td><input class="regular-text" type="number" min="60" name="serben[cache_ttl]" value="<?php echo esc_attr((string)($s['cache_ttl'] ?? '600')); ?>"><p class="description">Tempo em segundos. Padrão: 600 segundos.</p></td></tr>
                    <tr><th>Debug</th><td><label><input type="checkbox" name="serben[debug]" value="1" <?php checked($s['debug'], '1'); ?>> Registrar chamadas nos logs</label></td></tr>
                    <tr><th>Exibir detalhes técnicos no frontend</th><td><label><input type="checkbox" name="serben[show_technical_front]" value="1" <?php checked($s['show_technical_front'], '1'); ?>> Usar apenas durante testes</label></td></tr>
                </table>
                <?php submit_button('Salvar configurações'); ?>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:15px;">
                <?php wp_nonce_field('serben_test_connection'); ?>
                <input type="hidden" name="action" value="serben_test_connection">
                <?php submit_button('Testar conexão com CPF', 'secondary'); ?>
            </form>

            <h2>Shortcodes principais</h2>
            <p><code>[serben_app]</code>, <code>[serben_login]</code>, <code>[serben_logout]</code>, <code>[serben_lookup]</code>, <code>[serben_register]</code>, <code>[serben_register_submit]</code></p>
            <h3>Dashboard modular</h3>
            <p><code>[serben_name]</code>, <code>[serben_first_name]</code>, <code>[serben_cpf]</code>, <code>[serben_email]</code>, <code>[serben_phone]</code></p>
            <p><code>[serben_plan_name]</code>, <code>[serben_plan_status]</code>, <code>[serben_card_number]</code>, <code>[serben_card_status]</code></p>
            <p><code>[serben_points]</code>, <code>[serben_cashback]</code>, <code>[serben_payment_status]</code>, <code>[serben_next_due_date]</code></p>
            <p><code>[serben_card_points]</code>, <code>[serben_card_cashback]</code>, <code>[serben_card_plan]</code></p>
        </div>
        <?php
    }

    public function logsPage(): void
    {
        $logs = Logger::all();
        ?>
        <div class="wrap">
            <h1>Logs Serben Connect</h1>
            <?php if (!empty($_GET['cleared'])): ?><div class="notice notice-success"><p>Logs limpos.</p></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('serben_clear_logs'); ?>
                <input type="hidden" name="action" value="serben_clear_logs">
                <?php submit_button('Limpar logs', 'delete'); ?>
            </form>
            <table class="widefat striped">
                <thead><tr><th>Data</th><th>Nível</th><th>Método</th><th>Endpoint</th><th>HTTP</th><th>Mensagem</th><th>Contexto</th></tr></thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7">Nenhum log encontrado.</td></tr>
                <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['time']); ?></td>
                        <td><?php echo esc_html($log['level']); ?></td>
                        <td><?php echo esc_html($log['method']); ?></td>
                        <td><code><?php echo esc_html($log['endpoint']); ?></code></td>
                        <td><?php echo esc_html((string)$log['code']); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td><details><summary>ver</summary><pre style="white-space:pre-wrap;max-width:620px;overflow:auto;"><?php echo esc_html(print_r($log['context'], true)); ?></pre></details></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function componentsPage(): void
    {
        $components = (new ComponentRegistry())->all();
        ?>
        <div class="wrap">
            <h1>Biblioteca de Componentes</h1>
            <p>Use estes shortcodes em páginas, templates ou widgets do Elementor. Todos compartilham o mesmo DataProvider e cache do associado.</p>
            <table class="widefat striped">
                <thead><tr><th>Componente</th><th>Shortcode</th><th>Categoria</th><th>Descrição</th></tr></thead>
                <tbody>
                <?php foreach ($components as $component): ?>
                    <tr>
                        <td><strong><?php echo esc_html($component->title()); ?></strong></td>
                        <td><code>[<?php echo esc_html($component->shortcode()); ?>]</code></td>
                        <td><?php echo esc_html($component->category()); ?></td>
                        <td><?php echo esc_html($component->description()); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function environmentPage(): void
    {
        $report = (new EnvironmentInspector())->inspect();
        ?>
        <div class="wrap">
            <h1>Ambiente Serben Connect</h1>
            <p>Verificação consolidada da instalação, integrações e mapeamentos usados pelo plugin.</p>

            <table class="widefat striped" style="max-width:1000px">
                <thead><tr><th>Área</th><th>Item</th><th>Status / valor</th></tr></thead>
                <tbody>
                <?php foreach ($report as $area => $items): ?>
                    <?php foreach ((array) $items as $item => $value): ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucfirst((string) $area)); ?></strong></td>
                            <td><code><?php echo esc_html((string) $item); ?></code></td>
                            <td>
                                <?php if (is_bool($value)): ?>
                                    <span style="font-weight:600;color:<?php echo $value ? '#008a20' : '#b32d2e'; ?>">
                                        <?php echo $value ? 'OK' : 'Não detectado'; ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo esc_html((string) $value); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Relatório JSON</h2>
            <details><summary>Exibir relatório técnico</summary><pre style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #ccd0d4;max-width:1000px;overflow:auto;"><?php echo esc_html(wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre></details>
        </div>
        <?php
    }

    public function registrationFieldsPage(): void
    {
        $fields = FieldGlossary::all();
        ?>
        <div class="wrap">
            <h1>Glossário de campos de cadastro</h1>
            <p>Monte o formulário livremente no Elementor. Em cada campo, use exatamente o ID indicado abaixo e insira <code>[serben_register_submit]</code> onde deseja exibir o botão.</p>
            <p><strong>Exemplo:</strong> no campo CPF do Elementor, defina o ID como <code>serben_cpf_cnpj</code>.</p>
            <table class="widefat striped">
                <thead><tr><th>Campo</th><th>ID no Elementor</th><th>Campo da API</th><th>Obrigatório</th><th>Tipo</th><th>Formato</th></tr></thead>
                <tbody>
                <?php foreach ($fields as $apiField => $field): ?>
                    <tr>
                        <td><strong><?php echo esc_html($field['label']); ?></strong></td>
                        <td><code><?php echo esc_html($field['elementor_id']); ?></code></td>
                        <td><code><?php echo esc_html($apiField); ?></code></td>
                        <td><?php echo !empty($field['required']) ? 'Sim' : 'Não'; ?></td>
                        <td><?php echo esc_html($field['type']); ?></td>
                        <td><?php echo esc_html($field['format']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <h2>Shortcode do botão</h2>
            <p><code>[serben_register_submit]</code></p>
            <p><code>[serben_register_submit text="Finalizar cadastro" loading_text="Enviando..." success_url="/primeiro-acesso/"]</code></p>
            <p class="description">O shortcode procura os campos dentro do formulário mais próximo. Ele também reconhece automaticamente a estrutura de nomes usada pelo formulário do Elementor.</p>
        </div>
        <?php
    }

    public function dependentFieldsPage(): void
    {
        $fields = DependentFieldGlossary::all();
        ?>
        <div class="wrap">
            <h1>Glossário de campos de dependentes</h1>
            <p>Monte o formulário no Elementor usando os IDs abaixo. Insira <code>[serben_relationship_options]</code> para o seletor de parentesco e <code>[serben_dependent_submit]</code> para o botão.</p>
            <table class="widefat striped"><thead><tr><th>Campo</th><th>ID no Elementor</th><th>Campo da API</th><th>Obrigatório</th><th>Formato</th></tr></thead><tbody>
            <?php foreach ($fields as $apiField => $field): ?>
                <tr><td><strong><?php echo esc_html($field['label']); ?></strong></td><td><code><?php echo esc_html($field['elementor_id']); ?></code></td><td><code><?php echo esc_html($apiField); ?></code></td><td><?php echo !empty($field['required']) ? 'Sim' : 'Não'; ?></td><td><?php echo esc_html($field['format']); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <h2>Shortcodes</h2>
            <p><code>[serben_relationship_options]</code></p>
            <p><code>[serben_dependent_submit text="Cadastrar dependente"]</code></p>
        </div>
        <?php
    }

}
