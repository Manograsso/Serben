<?php
namespace SerbenConnect\Components;

use SerbenConnect\Domain\Member;
use SerbenConnect\Providers\UserDataProvider;

if (!defined('ABSPATH')) {
    exit;
}

abstract class BaseComponent
{
    protected $shortcode = '';
    protected $title = '';
    protected $category = 'Geral';
    protected $description = '';

    public function shortcode(): string
    {
        return $this->shortcode;
    }

    public function title(): string
    {
        return $this->title ?: $this->shortcode;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function renderShortcode($atts = []): string
    {
        wp_enqueue_style('serben-connect');
        $member = $this->member();
        if (!$member) {
            return '<span class="serben-component-empty">Faça login para visualizar.</span>';
        }
        return $this->render($member, is_array($atts) ? $atts : []);
    }

    abstract public function render(Member $member, array $atts = []): string;

    protected function member(): ?Member
    {
        return (new UserDataProvider())->current();
    }

    protected function value($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        return esc_html((string) $value);
    }

    protected function card(string $label, string $value, string $description = ''): string
    {
        $html = '<div class="serben-component-card">';
        $html .= '<span class="serben-component-label">' . esc_html($label) . '</span>';
        $html .= '<strong class="serben-component-value">' . esc_html($value ?: '—') . '</strong>';
        if ($description !== '') {
            $html .= '<small>' . esc_html($description) . '</small>';
        }
        $html .= '</div>';
        return $html;
    }
}
