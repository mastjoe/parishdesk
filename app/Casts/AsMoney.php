<?php

namespace App\Casts;

use App\Models\ParishSetting;
use App\Support\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a minor-unit integer column and its sibling currency column to Money.
 *
 * Usage: 'amount' => AsMoney::class.':currency'
 *
 * Anything other than Money or null is rejected when set, so a raw number
 * can never be stored without its currency.
 *
 * @implements CastsAttributes<Money, mixed>
 */
class AsMoney implements CastsAttributes
{
    public function __construct(
        protected string $currencyColumn = 'currency',
    ) {}

    /**
     * Cast the stored value to Money.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $attributes[$this->currencyColumn] ?? ParishSetting::current()->currency;

        return Money::of((int) $value, (string) $currency);
    }

    /**
     * Prepare Money for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException("The [{$key}] attribute must be set to a Money instance.");
        }

        return [$key => $value->amount, $this->currencyColumn => $value->currency];
    }
}
