<?php
namespace SerbenConnect\Diagnostics;

use SerbenConnect\Services\ClientesService;
use SerbenConnect\Providers\UserDataProvider;

if (!defined('ABSPATH')) { exit; }

/** Diagnostic visible only to administrators; never prints CPF, credentials or raw payload. */
final class WalletDiagnostic {
    public function register(): void {
        add_shortcode('serben_diagnostico_carteiras', [$this, 'render']);
    }

    public function render($atts = []): string {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return '<p>Diagnóstico disponível apenas para administradores autenticados.</p>';
        }
        $atts = shortcode_atts(['user_id' => ''], (array) $atts, 'serben_diagnostico_carteiras');
        $id = absint($atts['user_id']);
        if (!$id) {
            $id = get_current_user_id();
        }
        $target = get_userdata($id);
        if (!$target || !current_user_can('edit_user', $id)) {
            return '<p>Usuário de diagnóstico inexistente ou sem permissão de acesso.</p>';
        }
        $keys = ['serben_documento', 'serben_cpf', 'cpf', 'billing_cpf', 'billing_cnpj'];
        $selectedKey = '';
        $document = '';
        $present = [];
        foreach ($keys as $key) {
            $value = preg_replace('/\D+/', '', (string) get_user_meta($id, $key, true));
            if (strlen($value) === 11 || strlen($value) === 14) {
                $present[] = $key;
                if (!$document) { $document = $value; $selectedKey = $key; }
            }
        }
        $rows = [
            'Usuário WordPress analisado (ID)' => (string) $id,
            'Chave de documento utilizada' => $selectedKey ?: 'NENHUMA',
            'Outras chaves válidas' => $present ? implode(', ', $present) : 'Nenhuma',
            'Documentos divergentes nas chaves' => count(array_unique(array_filter(array_map(static function($key) use ($id) {
                $v = preg_replace('/\D+/', '', (string) get_user_meta($id, $key, true));
                return in_array(strlen($v), [11, 14], true) ? $v : '';
            }, $keys)))) > 1 ? 'SIM — revisar vínculo' : 'Não',
        ];
        if (!$document) {
            $rows['Consulta'] = 'Não realizada: CPF/CNPJ não vinculado ao usuário logado.';
        } else {
            // Fresh, independent request: deliberately bypasses profile transient and static Member cache.
            $service = new ClientesService();
            $result = $service->buscarPorDocumento($document);
            $data = $service->extractCliente($result);
            $data = is_array($data) ? $data : [];
            $cards = isset($data['cartoes']) && is_array($data['cartoes']) ? $data['cartoes'] : [];
            $rows['HTTP da API (consulta direta)'] = (string) ($result['code'] ?? 0);
            $rows['API ok'] = !empty($result['ok']) ? 'Sim' : 'Não';
            $rows['Portador encontrado'] = $data ? 'Sim' : 'Não';
            $rows['Cartões retornados'] = (string) count($cards);
            $rows['Cartão ativo'] = (string) ($data['status_cartao'] ?? 'Não encontrado');
            foreach (['saldo_cashback_liberado', 'saldo_pontos_liberado', 'saldo_credito', 'saldo_cci_liberado', 'saldo_cci_disponivel', 'saldo_cci'] as $key) {
                $rows['API: '.$key] = array_key_exists($key, $data) ? (is_numeric($data[$key]) ? (string) $data[$key] : 'Presente, não numérico') : 'Campo ausente';
            }
            // O mesmo provider dos cards, agora executado para o usuário selecionado,
            // sem simular login ou alterar a sessão do administrador.
            $member = (new UserDataProvider())->forUser($id, true);
            $rows['Provider: Cashback'] = $member ? $member->cashback()->formatted() : 'Sem portador';
            $rows['Provider: Bumes / Pontos'] = $member ? $member->points()->formatted() : 'Sem portador';
            $rows['Provider: NET BUME / CASH BUME'] = $member ? $member->cashBume()->formatted() : 'Sem portador';
            $rows['Provider: Créditos'] = $member ? $member->financial()->creditFormatted() : 'Sem portador';
            if ($member) {
                $raw = $member->raw();
                $client = $raw['cliente'] ?? [];
                $rows['Provider: campo cashback presente'] = array_key_exists('saldo_cashback_liberado', $client) ? 'Sim' : 'Não';
                $rows['Provider: campo pontos presente'] = array_key_exists('saldo_pontos_liberado', $client) ? 'Sim' : 'Não';
                $rows['Provider: HTTP'] = (string) ($raw['club_meta']['http_code'] ?? 0);
            }
            $rows['Observação'] = 'Valores brutos da API em centavos para saldos monetários; pontos sem conversão.';
        }
        $html = '<section class="serben-wallet-diagnostic"><h3>Diagnóstico das carteiras — acesso administrativo (usuário selecionado)</h3><table><tbody>';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th style="text-align:left;padding:6px">'.esc_html($label).'</th><td style="padding:6px">'.esc_html($value).'</td></tr>';
        }
        return $html.'</tbody></table><p>Esta consulta não exibe CPF, nome, e-mail, tokens ou resposta bruta da API. Não publique capturas com informações pessoais.</p></section>';
    }
}
