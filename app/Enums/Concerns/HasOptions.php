<?php

namespace App\Enums\Concerns;

/**
 * Lists a labelled enum's cases for select inputs on the frontend.
 *
 * @phpstan-require-implements \BackedEnum
 */
trait HasOptions
{
    /**
     * Get every case as a value and label pair.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case): array => ['value' => (string) $case->value, 'label' => $case->label()], self::cases());
    }

    /**
     * Get every case value.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => (string) $case->value, self::cases());
    }
}
