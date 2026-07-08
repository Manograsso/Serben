<?php
namespace SerbenConnect\Admin;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Support\Logger;
use SerbenConnect\Support\Settings;

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
        $result = (new ClientesService())->buscarPorDocumento($cpf);
        set_transient('serben_connect_last_test', $result, 120);
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
        ?>
        <div class="wrap">
            <h1>Serben Connect</h1>
            <?php if (!empty($_GET['updated'])): ?><div class="notice notice-success"><p>Configurações salvas.</p></div><?php endif; ?>
            <?php if (!empty($_GET['tested']) && is_array($last)): ?>
                <div class="notice <?php echo !empty($last['ok']) ? 'notice-success' : 'notice-error'; ?>">
                    <p><strong>Teste:</strong> HTTP <?php echo esc_html((string)($last['code'] ?? '0')); ?> — <?php echo !empty($last['ok']) ? 'conectado com sucesso.' : 'falha na chamada.'; ?></p>
                    <p><strong>URL:</strong> <code><?php echo esc_html($last['url'] ?? ''); ?></code></p>
                    <details><summary>Resposta da API</summary><pre style="white-space:pre-wrap;background:#fff;padding:12px;border:1px solid #ccd0d4;max-height:350px;overflow:auto;"><?php echo esc_html(print_r($last['body'] ?? $last['raw'] ?? '', true)); ?></pre></details>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('serben_save_settings'); ?>
                <input type="hidden" name="action" value="serben_save_settings">
                <table class="form-table" role="presentation">
                    <tr><th>URL Base</th><td><input class="regular-text" name="serben[base_url]" value="<?php echo esc_attr($s['base_url']); ?>"><p class="description">Ex.: https://serben.conectar.site</p></td></tr>
                    <tr><th>x-api-key</th><td><input class="regular-text" name="serben[api_key]" value="<?php echo esc_attr($s['api_key']); ?>"></td></tr>
                    <tr><th>IDENTIFIER</th><td><input class="regular-text" name="serben[identifier]" value="<?php echo esc_attr($s['identifier']); ?>"></td></tr>
                    <tr><th>ID Loja</th><td><input class="regular-text" name="serben[id_loja]" value="<?php echo esc_attr($s['id_loja']); ?>"></td></tr>
                    <tr><th>CNPJ Empresa</th><td><input class="regular-text" name="serben[cnpj_empresa]" value="<?php echo esc_attr($s['cnpj_empresa']); ?>"></td></tr>
                    <tr><th>Código padrão</th><td><input class="regular-text" name="serben[codigo]" value="<?php echo esc_attr($s['codigo']); ?>"></td></tr>
                    <tr><th>CPF de teste</th><td><input class="regular-text" name="serben[test_cpf]" value="<?php echo esc_attr($s['test_cpf']); ?>"></td></tr>
                    <tr><th>URL da página de cadastro</th><td><input class="regular-text" name="serben[register_page_url]" value="<?php echo esc_attr($s['register_page_url']); ?>"><p class="description">Página que contém <code>[serben_register]</code>. Será usada quando o CPF não existir no app.</p></td></tr>
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

            <h2>Shortcodes</h2>
            <p><code>[serben_app]</code> — entrada principal: login com CPF + senha, primeiro acesso e área inicial do associado.</p>
            <p><code>[serben_login]</code> — formulário de login e primeiro acesso.</p>
            <p><code>[serben_logout]</code> — botão de saída.</p>
            <p><code>[serben_lookup]</code> — apenas consulta por CPF, sem login.</p>
            <p><code>[serben_register]</code> — apenas formulário de cadastro.</p>
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
}
