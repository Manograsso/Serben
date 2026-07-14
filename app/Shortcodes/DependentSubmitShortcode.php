<?php
namespace SerbenConnect\Shortcodes;

use SerbenConnect\Dependents\FieldGlossary;

if (!defined('ABSPATH')) { exit; }

class DependentSubmitShortcode
{
    public function register(): void { add_shortcode('serben_dependent_submit',[$this,'render']); }
    public function render(array $atts=[]): string
    {
        if(!is_user_logged_in()) return '<span class="serben-component-empty">Faça login para cadastrar um dependente.</span>';
        $atts=shortcode_atts(['text'=>'Cadastrar dependente','loading_text'=>'Enviando...','success_url'=>'','class'=>''],$atts,'serben_dependent_submit');
        wp_enqueue_style('serben-connect'); wp_enqueue_script('serben-dependent-submit');
        wp_localize_script('serben-dependent-submit','SerbenDependentRegistration',['ajaxUrl'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('serben_dependent_register'),'fieldMap'=>FieldGlossary::apiMap()]);
        return '<div class="serben-register-submit-wrap"><button type="button" class="serben-dependent-submit '.esc_attr(sanitize_html_class((string)$atts['class'])).'" data-loading-text="'.esc_attr((string)$atts['loading_text']).'" data-success-url="'.esc_url((string)$atts['success_url']).'">'.esc_html((string)$atts['text']).'</button><div class="serben-dependent-message" aria-live="polite"></div></div>';
    }
}
