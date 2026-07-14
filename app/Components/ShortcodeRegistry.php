<?php
namespace SerbenConnect\Components;

if (!defined('ABSPATH')) {
    exit;
}

class ShortcodeRegistry
{
    private $components;
    private $engine;

    public function __construct(ComponentRegistry $components)
    {
        $this->components = $components;
        $this->engine = new ComponentEngine($components);
    }

    public function register(): void
    {
        foreach ($this->components->all() as $component) {
            if ($component instanceof BaseComponent && $component->shortcode()) {
                add_shortcode($component->shortcode(), function ($atts = []) use ($component): string {
                    return $this->engine->render($component->shortcode(), is_array($atts) ? $atts : []);
                });
            }
        }
    }
}
