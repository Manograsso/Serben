<?php
namespace SerbenConnect\Integrations\Awin;

if (!defined('ABSPATH')) { exit; }

final class Shortcodes
{
    public function register(): void
    {
        add_shortcode('serben_partner_link', [$this, 'link']);
        add_shortcode('serben_partner_awin_link', [$this, 'link']); // compatibilidade
        add_shortcode('serben_awin_cashback_history', [$this, 'history']);
        add_shortcode('serben_awin_cashback_pending', [$this, 'pending']);
    }

    public function link($atts = []): string
    {
        $a = shortcode_atts([
            'id' => 0,
            'text' => '',
            'destination' => '',
            'class' => '',
        ], $atts, 'serben_partner_link');

        $id = absint($a['id']) ?: get_queried_object_id();
        if (!$id) { return ''; }

        $advertiserId = (int) get_post_meta($id, 'awin_advertiser_id', true);
        $awinStatus = strtolower(trim((string) get_post_meta($id, 'awin_program_status', true)));
        $cashbackEnabled = get_post_meta($id, 'awin_cashback_enabled', true) === '1';
        $cashbackPercent = (float) get_post_meta($id, 'awin_cashback_percent', true);
        $destination = esc_url_raw((string) $a['destination']);
        $text = trim((string) $a['text']);
        $url = '';
        $isTrackedCashback = false;

        if ($advertiserId > 0) {
            // Parceiro gerenciado pela Awin: se não estiver Active, não deve gerar link no frontend.
            if ($awinStatus !== 'active') { return ''; }

            if ($cashbackEnabled && $cashbackPercent > 0) {
                $url = (new TrackingService())->url($id, $destination);
                $isTrackedCashback = true;
                if ($text === '') {
                    $pct = rtrim(rtrim(number_format($cashbackPercent, 2, ',', ''), '0'), ',');
                    $text = 'Comprar e ganhar ' . $pct . '% de cashback';
                }
            } else {
                $url = (string) get_post_meta($id, 'awin_click_through_url', true);
                if ($url === '') { $url = (string) get_post_meta($id, 'link_afiliado', true); }
                if ($url === '') { $url = (string) get_post_meta($id, 'site', true); }
                if ($text === '') { $text = 'Acessar parceiro'; }
            }
        } else {
            // Parceiro fora da Awin: usa os links editoriais existentes no WordPress.
            $url = (string) get_post_meta($id, 'link_afiliado', true);
            if ($url === '') { $url = (string) get_post_meta($id, 'site', true); }
            if ($text === '') { $text = 'Acessar parceiro'; }
        }

        if ($url === '') { return ''; }

        $classes = 'serben-partner-link';
        if ($isTrackedCashback) { $classes .= ' serben-awin-link'; }
        if ($a['class'] !== '') { $classes .= ' ' . sanitize_html_class((string) $a['class']); }

        return '<a class="' . esc_attr($classes) . '" href="' . esc_url($url) . '" rel="sponsored nofollow">' . esc_html($text) . '</a>';
    }

    public function history($atts = []): string
    {
        if (!is_user_logged_in()) { return ''; }
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}serben_awin_transactions WHERE user_id=%d ORDER BY transaction_date DESC, id DESC LIMIT 50",
            get_current_user_id()
        ), ARRAY_A);
        if (!$rows) { return '<p>Nenhuma compra Awin localizada.</p>'; }

        $html = '<table class="serben-awin-history"><thead><tr><th>Data</th><th>Parceiro</th><th>Compra</th><th>Cashback</th><th>Status</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $title = $r['partner_id'] ? get_the_title((int) $r['partner_id']) : ('Awin #' . $r['advertiser_id']);
            $html .= '<tr><td>' . esc_html((string) $r['transaction_date']) . '</td><td>' . esc_html($title) . '</td><td>' . esc_html($r['currency'] . ' ' . number_format((float) $r['sale_amount'], 2, ',', '.')) . '</td><td>' . esc_html('R$ ' . number_format(((int) $r['cashback_cents']) / 100, 2, ',', '.')) . '</td><td>' . esc_html((string) $r['serben_status']) . '</td></tr>';
        }
        return $html . '</tbody></table>';
    }

    public function pending(): string
    {
        if (!is_user_logged_in()) { return ''; }
        global $wpdb;
        $c = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}serben_awin_transactions WHERE user_id=%d AND serben_status='waiting'",
            get_current_user_id()
        ));
        return (string) $c;
    }
}
