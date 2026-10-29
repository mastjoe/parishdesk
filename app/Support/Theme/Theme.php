<?php

namespace App\Support\Theme;

use App\Models\ParishSetting;

/**
 * Resolves the parish theme into what the root template and the UI need.
 */
class Theme
{
    /**
     * The preset themes, keyed by the value stored on the parish settings.
     *
     * The CSS for each preset lives in resources/css/themes.css.
     *
     * @var array<string, array{label: string, swatch: string}>
     */
    public const array PRESETS = [
        'marian' => ['label' => 'Marian Blue', 'swatch' => '#2f5fb3'],
        'ordinary' => ['label' => 'Ordinary Green', 'swatch' => '#2e7d4f'],
        'penitence' => ['label' => 'Advent Purple', 'swatch' => '#6b3fa0'],
        'martyr' => ['label' => 'Martyr Red', 'swatch' => '#b3261e'],
        'gold' => ['label' => 'Papal Gold', 'swatch' => '#b8860b'],
        'gaudete' => ['label' => 'Gaudete Rose', 'swatch' => '#c2577f'],
        'slate' => ['label' => 'Slate', 'swatch' => '#334155'],
    ];

    /**
     * The value stored when the parish uses its own colour.
     */
    public const string CUSTOM = 'custom';

    /**
     * Get every theme value an admin may choose.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [...array_keys(self::PRESETS), self::CUSTOM];
    }

    /**
     * Get the theme value to write on the root element.
     */
    public static function active(ParishSetting $settings): string
    {
        if ($settings->theme === self::CUSTOM && $settings->custom_color !== null) {
            return self::CUSTOM;
        }

        return array_key_exists($settings->theme, self::PRESETS) ? $settings->theme : 'marian';
    }

    /**
     * Get the inline CSS for a custom colour, or null for presets.
     */
    public static function inlineCss(ParishSetting $settings): ?string
    {
        if (self::active($settings) !== self::CUSTOM) {
            return null;
        }

        return CustomPalette::fromHex((string) $settings->custom_color)->css();
    }
}
