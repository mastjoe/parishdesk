<?php

namespace App\Support\Theme;

use InvalidArgumentException;

/**
 * Builds the theme CSS variables for a parish-chosen hex colour.
 *
 * The colour is converted to OKLCH so the lightness can be clamped into a
 * readable range for both light and dark mode while keeping its hue.
 */
final readonly class CustomPalette
{
    private function __construct(
        public float $lightness,
        public float $chroma,
        public float $hue,
    ) {}

    /**
     * Create a palette from a "#rrggbb" colour.
     */
    public static function fromHex(string $hex): self
    {
        if (! preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $hex, $matches)) {
            throw new InvalidArgumentException("[{$hex}] is not a valid hex colour.");
        }

        [$red, $green, $blue] = array_map(
            fn (string $channel): float => self::toLinear(hexdec($channel) / 255),
            array_slice($matches, 1),
        );

        $long = self::cubeRoot(0.4122214708 * $red + 0.5363325363 * $green + 0.0514459929 * $blue);
        $medium = self::cubeRoot(0.2119034982 * $red + 0.6806995451 * $green + 0.1073969566 * $blue);
        $short = self::cubeRoot(0.0883024619 * $red + 0.2817188376 * $green + 0.6299787005 * $blue);

        $lightness = 0.2104542553 * $long + 0.7936177850 * $medium - 0.0040720468 * $short;
        $a = 1.9779984951 * $long - 2.4285922050 * $medium + 0.4505937099 * $short;
        $b = 0.0259040371 * $long + 0.7827717662 * $medium - 0.8086757660 * $short;

        $hue = rad2deg(atan2($b, $a));

        return new self($lightness, sqrt($a ** 2 + $b ** 2), $hue < 0 ? $hue + 360 : $hue);
    }

    /**
     * Get the CSS rules for the custom theme in light and dark mode.
     */
    public function css(): string
    {
        $light = $this->variables(lightness: $this->clamp($this->lightness, 0.38, 0.62), dark: false);
        $dark = $this->variables(lightness: $this->clamp($this->lightness + 0.2, 0.68, 0.82), dark: true);

        return ":root[data-theme=\"custom\"]{{$light}}:root.dark[data-theme=\"custom\"]{{$dark}}";
    }

    /**
     * Get the foreground colour with the better WCAG contrast against a background lightness.
     */
    public function foregroundFor(float $lightness): string
    {
        $luminance = $lightness ** 3;
        $contrastWithWhite = 1.05 / ($luminance + 0.05);
        $contrastWithDark = ($luminance + 0.05) / 0.05;

        return $contrastWithWhite >= $contrastWithDark
            ? 'oklch(0.985 0 0)'
            : $this->oklch(0.2, 0.02, $this->hue);
    }

    /**
     * Build the variable declarations for one mode.
     */
    private function variables(float $lightness, bool $dark): string
    {
        $chroma = min($this->chroma, 0.2);
        $primary = $this->oklch($lightness, $chroma, $this->hue);
        $foreground = $this->foregroundFor($lightness);

        $accent = $dark
            ? $this->oklch(0.28, min($chroma, 0.04), $this->hue)
            : $this->oklch(0.96, min($chroma, 0.03), $this->hue);
        $accentForeground = $dark
            ? 'oklch(0.97 0 0)'
            : $this->oklch(0.3, $chroma, $this->hue);

        $declarations = [
            '--primary' => $primary,
            '--primary-foreground' => $foreground,
            '--ring' => $primary,
            '--accent' => $accent,
            '--accent-foreground' => $accentForeground,
            '--sidebar-primary' => $primary,
            '--sidebar-primary-foreground' => $foreground,
            '--sidebar-accent' => $accent,
            '--sidebar-accent-foreground' => $accentForeground,
            '--sidebar-ring' => $primary,
        ];

        foreach ([0, 40, 160, 220, 300] as $index => $rotation) {
            $declarations['--chart-'.($index + 1)] = $this->oklch(
                $lightness,
                $chroma,
                fmod($this->hue + $rotation, 360),
            );
        }

        return collect($declarations)
            ->map(fn (string $value, string $name): string => "{$name}:{$value};")
            ->implode('');
    }

    /**
     * Format an OKLCH colour.
     */
    private function oklch(float $lightness, float $chroma, float $hue): string
    {
        return sprintf('oklch(%.3f %.3f %.1f)', $lightness, $chroma, $hue);
    }

    /**
     * Convert a gamma encoded sRGB channel to linear light.
     */
    private static function toLinear(float $channel): float
    {
        return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }

    /**
     * Get the real cube root of a value.
     */
    private static function cubeRoot(float $value): float
    {
        return $value < 0 ? -((-$value) ** (1 / 3)) : $value ** (1 / 3);
    }

    /**
     * Clamp a value between a minimum and maximum.
     */
    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}
