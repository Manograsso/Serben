<?php
namespace SerbenConnect\Domain;

if (!defined('ABSPATH')) {
    exit;
}

class DataExtractor
{
    public static function first(array $sources, array $keys, $default = '')
    {
        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }
            foreach ($keys as $key) {
                $value = self::get($source, $key, null);
                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }
        return $default;
    }

    public static function get(array $source, string $path, $default = null)
    {
        $current = $source;
        foreach (explode('.', $path) as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
                continue;
            }
            return $default;
        }
        return $current;
    }

    public static function money($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_string($value)) {
            $normalized = str_replace(['R$', ' ', '.'], '', $value);
            $normalized = str_replace(',', '.', $normalized);
            if (is_numeric($normalized)) {
                $value = (float) $normalized;
            }
        }
        if (is_numeric($value)) {
            $float = (float) $value;
            if ($float > 999 && floor($float) == $float) {
                $float = $float / 100;
            }
            return 'R$ ' . number_format($float, 2, ',', '.');
        }
        return (string) $value;
    }

    public static function number($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 0, ',', '.');
        }
        return (string) $value;
    }
}
