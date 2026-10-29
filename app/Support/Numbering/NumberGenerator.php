<?php

namespace App\Support\Numbering;

use Illuminate\Support\Facades\DB;

/**
 * Hands out sequential reference numbers such as "MEM-2026-0001".
 *
 * Formats support the tokens {Y} (year), {m} (month) and {seq:N} (the
 * counter padded to N digits). The counter row is locked while it is read
 * and advanced, so concurrent requests never receive the same number.
 */
class NumberGenerator
{
    /**
     * Get the next number for a sequence, creating the sequence when missing.
     */
    public function next(string $key, string $defaultFormat, bool $resetsYearly = false): string
    {
        return DB::transaction(function () use ($key, $defaultFormat, $resetsYearly): string {
            $year = (int) now()->format('Y');

            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key,
                'format' => $defaultFormat,
                'next_number' => 1,
                'resets_yearly' => $resetsYearly,
                'current_year' => $year,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();

            $number = (int) $sequence->next_number;

            if ($sequence->resets_yearly && (int) $sequence->current_year !== $year) {
                $number = 1;
            }

            DB::table('number_sequences')->where('key', $key)->update([
                'next_number' => $number + 1,
                'current_year' => $year,
                'updated_at' => now(),
            ]);

            return $this->format((string) $sequence->format, $number);
        });
    }

    /**
     * Replace the tokens in a format with the current date and counter.
     */
    public function format(string $format, int $number): string
    {
        $formatted = strtr($format, [
            '{Y}' => now()->format('Y'),
            '{m}' => now()->format('m'),
        ]);

        return (string) preg_replace_callback(
            '/\{seq(?::(\d+))?\}/',
            fn (array $matches): string => str_pad((string) $number, (int) ($matches[1] ?? 1), '0', STR_PAD_LEFT),
            $formatted,
        );
    }
}
