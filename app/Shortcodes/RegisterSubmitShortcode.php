<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Registration\FieldGlossary;

if (!defined('ABSPATH')) {
    exit;
}

class RegisterSubmitShortcode
{
    public function register(): void
    {
        add_shortcode('serben_register_submit', [$this, 'render']);
    }

    public function render(array $atts = []): string
    {
        $atts = shortcode_atts([
            'text' => 'Criar cadastro',
            'loading_text' => 'Enviando...',
            'success_url' => '',
            'class' => '',
        ], $atts, 'serben_register_submit');

        wp_enqueue_style('serben-connect');
        wp_enqueue_script('serben-register-submit');
        wp_localize_script('serben-register-submit', 'SerbenRegistration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('serben_external_register'),
            'fieldMap' => FieldGlossary::apiMap(),
        ]);

        $class = 'serben-register-submit ' . sanitize_html_class((string)$atts['class']);
        return '<div class="serben-register-submit-wrap">'
            . '<button type="button" class="' . esc_attr(trim($class)) . '" data-loading-text="' . esc_attr((string)$atts['loading_text']) . '" data-success-url="' . esc_url((string)$atts['success_url']) . '">' . esc_html((string)$atts['text']) . '</button>'
            . '<div class="serben-register-message" aria-live="polite"></div>'
            . '</div>';
    }
}
