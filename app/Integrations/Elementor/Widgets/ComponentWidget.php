<?php
namespace SerbenConnect\Integrations\Elementor\Widgets;

use SerbenConnect\Components\ComponentEngine;

if (!defined('ABSPATH')) {
    exit;
}

final class ComponentWidget extends \Elementor\Widget_Base
{
    private $definition = [];

    public function __construct(array $definition = [], $data = [], $args = null)
    {
        $this->definition = $definition;
        parent::__construct($data, $args);
    }

    public function get_name()
    {
        return (string) ($this->definition['name'] ?? 'serben-component');
    }

    public function get_title()
    {
        return (string) ($this->definition['title'] ?? 'Serben Connect');
    }

    public function get_icon()
    {
        return (string) ($this->definition['icon'] ?? 'eicon-code');
    }

    public function get_categories()
    {
        return ['serben-connect'];
    }

    public function get_keywords()
    {
        return ['serben', 'clube', strtolower((string) ($this->definition['group'] ?? 'componente'))];
    }

    protected function register_controls()
    {
        $this->start_controls_section('serben_content', [
            'label' => 'Conteúdo',
            'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
        ]);

        if (!empty($this->definition['partner_id'])) {
            $this->add_control('partner_id', [
                'label' => 'ID do parceiro',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 0,
                'default' => 0,
                'description' => 'Use 0 para o parceiro atual em um template Single.',
            ]);
        }

        if (!empty($this->definition['listing'])) {
            $this->add_control('limit', [
                'label' => 'Quantidade',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => -1,
                'default' => 12,
            ]);
            $this->add_control('pagination', [
                'label' => 'Paginação',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Sim',
                'label_off' => 'Não',
                'return_value' => 'yes',
                'default' => 'yes',
            ]);
            $this->add_control('show_count', [
                'label' => 'Mostrar contador',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Sim',
                'label_off' => 'Não',
                'return_value' => 'yes',
                'default' => 'yes',
            ]);
            $this->add_control('orderby', [
                'label' => 'Ordenar por',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'title',
                'options' => [
                    'title' => 'Nome',
                    'date' => 'Data',
                    'featured' => 'Destaque',
                    'cashback' => 'Cashback',
                ],
            ]);
            $this->add_control('order', [
                'label' => 'Ordem',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'ASC',
                'options' => ['ASC' => 'Crescente', 'DESC' => 'Decrescente'],
            ]);
        }

        $this->end_controls_section();

        $this->start_controls_section('serben_style', [
            'label' => 'Estilo',
            'tab' => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('alignment', [
            'label' => 'Alinhamento',
            'type' => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'left' => ['title' => 'Esquerda', 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => 'Centro', 'icon' => 'eicon-text-align-center'],
                'right' => ['title' => 'Direita', 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => ['{{WRAPPER}} .serben-elementor-component' => 'text-align: {{VALUE}};'],
        ]);

        $this->add_control('text_color', [
            'label' => 'Cor do texto',
            'type' => \Elementor\Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .serben-elementor-component' => 'color: {{VALUE}};',
                '{{WRAPPER}} .serben-elementor-component a' => 'color: {{VALUE}};',
            ],
        ]);

        if (class_exists('Elementor\\Group_Control_Typography')) {
            $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .serben-elementor-component',
            ]);
        }

        $this->end_controls_section();
    }

    protected function render()
    {
        wp_enqueue_style('serben-connect');
        $settings = $this->get_settings_for_display();
        $attributes = [];

        if (!empty($this->definition['partner_id'])) {
            $attributes['id'] = (string) absint($settings['partner_id'] ?? 0);
        }
        if (!empty($this->definition['partner_field'])) {
            $attributes['field'] = (string) $this->definition['partner_field'];
            $attributes['format'] = (string) ($this->definition['format'] ?? (!empty($this->definition['image']) ? 'url' : 'text'));
        }
        if (!empty($this->definition['listing'])) {
            $attributes['limit'] = (string) intval($settings['limit'] ?? 12);
            $attributes['pagination'] = !empty($settings['pagination']) ? 'yes' : 'no';
            $attributes['show_count'] = !empty($settings['show_count']) ? 'yes' : 'no';
            $attributes['orderby'] = sanitize_key((string) ($settings['orderby'] ?? 'title'));
            $attributes['order'] = strtoupper((string) ($settings['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        }

        $output = (new ComponentEngine())->render((string) ($this->definition['component'] ?? ''), $attributes);
        if (!empty($this->definition['image']) && $output !== '') {
            $url = esc_url(trim(wp_strip_all_tags($output)));
            $output = $url ? '<img class="serben-elementor-partner-image" src="' . $url . '" alt="" loading="lazy">' : '';
        }

        echo '<div class="serben-elementor-component serben-elementor-component--' . esc_attr($this->get_name()) . '">' . wp_kses_post($output) . '</div>';
    }

    protected function content_template()
    {
        echo '<div class="serben-elementor-component"><em>Os dados Serben serão exibidos na visualização da página.</em></div>';
    }
}
