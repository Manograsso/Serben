<?php
namespace SerbenConnect\Components;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Motor central de componentes.
 *
 * Shortcodes, integrações futuras com Elementor e a API REST podem usar esta
 * classe para renderizar o mesmo componente sem duplicar regras de negócio.
 */
final class ComponentEngine
{
    /** @var ComponentRegistry */
    private $registry;

    public function __construct(?ComponentRegistry $registry = null)
    {
        $this->registry = $registry ?: new ComponentRegistry();
    }

    public function render(string $component, array $attributes = []): string
    {
        $instance = $this->registry->find($component);
        if (!$instance) {
            return '';
        }

        return $instance->renderShortcode($attributes);
    }

    public function exists(string $component): bool
    {
        return $this->registry->find($component) instanceof BaseComponent;
    }

    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->registry->all() as $component) {
            if (!$component instanceof BaseComponent) {
                continue;
            }
            $catalog[] = [
                'key' => $component->shortcode(),
                'shortcode' => $component->shortcode(),
                'title' => $component->title(),
                'category' => $component->category(),
                'description' => $component->description(),
            ];
        }

        return $catalog;
    }
}
