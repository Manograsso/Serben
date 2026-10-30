<?php
/**
 * Archive /credenciados/ — mesma experiência visual e funcional da página Ofertas.
 */
defined('ABSPATH') || exit;

get_header();

if (class_exists('Serben_Credenciados_Page_V9')) {
    echo Serben_Credenciados_Page_V9::shortcode_page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} elseif (shortcode_exists('serben_ofertas_page')) {
    echo do_shortcode('[serben_ofertas_page]'); // fallback seguro
} else {
    echo '<main style="padding:60px 24px;text-align:center"><h1>Ofertas Club Serben</h1><p>Não foi possível carregar a vitrine de ofertas.</p></main>';
}

get_footer();
