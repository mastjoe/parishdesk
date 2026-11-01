<?php

namespace App\Support\People;

use Illuminate\Support\Str;

/**
 * The occupations a member can be recorded with.
 *
 * The list is the ILO's International Standard Classification of Occupations
 * (ISCO-08), one entry per unit group, plus a few entries for people not in
 * paid work. Members store the code: the four-digit ISCO code, or a word for
 * the extra entries.
 *
 * @see https://isco.ilo.org/en/isco-08
 */
class Occupations
{
    /**
     * The code for an occupation that is not on the list.
     */
    public const string OTHER = 'other';

    /**
     * Common ways of writing a job that the word matching would get wrong.
     */
    private const array ALIASES = [
        'doctor' => '2211', 'medical doctor' => '2211', 'physician' => '2211',
        'nurse' => '2221', 'midwife' => '2222',
        'teacher' => '2330', 'lecturer' => '2310',
        'trader' => '5211', 'market trader' => '5211', 'business man' => '5221', 'businessman' => '5221',
        'business woman' => '5221', 'businesswoman' => '5221', 'shop owner' => '5221',
        'priest' => '2636', 'reverend father' => '2636', 'rev father' => '2636', 'reverend sister' => '2636',
        'nun' => '2636', 'pastor' => '2636', 'catechist' => '3413',
        'farmer' => '6130', 'driver' => '8322', 'mechanic' => '7231', 'soldier' => '0310',
        'banker' => '2412', 'engineer' => '2149', 'carpenter' => '7115', 'electrician' => '7411',
        'civil servant' => self::OTHER, 'public servant' => self::OTHER,
        'housewife' => 'homemaker', 'house wife' => 'homemaker', 'pensioner' => 'retired',
        'pupil' => 'student', 'corper' => 'student', 'nysc' => 'student', 'job seeker' => 'unemployed',
    ];

    /**
     * @var list<array{code: string, label: string, group: string}>|null
     */
    private static ?array $all = null;

    /**
     * Get every occupation as code, label and group.
     *
     * @return list<array{code: string, label: string, group: string}>
     */
    public static function all(): array
    {
        return self::$all ??= self::load();
    }

    /**
     * Get every occupation code.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::all(), 'code');
    }

    /**
     * Get the label of an occupation code.
     */
    public static function label(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        foreach (self::all() as $occupation) {
            if ($occupation['code'] === $code) {
                return $occupation['label'];
            }
        }

        return $code;
    }

    /**
     * Find the occupation a free-text job title most likely means, falling back to Other.
     *
     * Words are compared allowing for plurals and endings, so "nurse" matches
     * "Nursing Professionals". The occupation sharing the most words wins, and
     * among equals the shortest label, as the most general.
     */
    public static function guess(string $title): string
    {
        $needle = Str::lower(Str::squish($title));

        if (isset(self::ALIASES[$needle])) {
            return self::ALIASES[$needle];
        }

        foreach (self::all() as $occupation) {
            if ($needle === $occupation['code'] || $needle === Str::lower($occupation['label'])) {
                return $occupation['code'];
            }
        }

        $words = self::words($needle);
        $best = null;
        $bestScore = 0;

        foreach (self::all() as $occupation) {
            $labelWords = self::words(Str::lower($occupation['label']));
            $score = count(array_filter($words, fn (string $word): bool => array_any($labelWords, fn (string $labelWord): bool => self::sameWord($word, $labelWord))));

            if ($score > $bestScore || ($score > 0 && $score === $bestScore && strlen($occupation['label']) < strlen($best['label'] ?? ''))) {
                $best = $occupation;
                $bestScore = $score;
            }
        }

        return $best['code'] ?? self::OTHER;
    }

    /**
     * Read the occupation list from its data file.
     *
     * @return list<array{code: string, label: string, group: string}>
     */
    private static function load(): array
    {
        /** @var list<array{code: string, label: string, group: string}> */
        return json_decode((string) file_get_contents(resource_path('data/occupations.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Split text into the words worth comparing.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $words = preg_split('/[^a-z]+/', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, fn (string $word): bool => strlen($word) > 2 && ! in_array($word, ['and', 'the', 'for', 'other', 'related', 'workers', 'senior', 'junior', 'staff', 'trainee', 'general', 'elsewhere', 'classified', 'not'], true)));
    }

    /**
     * Whether two words share a stem, e.g. "teacher" and "teachers" or "nurse" and "nursing".
     */
    private static function sameWord(string $a, string $b): bool
    {
        $stem = fn (string $word): string => (string) preg_replace('/(ing|ers|er|es|s|e)$/', '', $word);

        return $a === $b || ($stem($a) !== '' && $stem($a) === $stem($b));
    }
}
