<?php

namespace App\Support\Money;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

/**
 * An amount of money held in minor units (kobo, cents) to avoid float errors.
 */
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    /**
     * Create money from an amount in minor units.
     */
    public static function of(int $amount, string $currency): self
    {
        return new self($amount, strtoupper($currency));
    }

    /**
     * Create money from a major unit amount such as "1250.50".
     */
    public static function fromMajor(string|int|float $amount, string $currency): self
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("[{$amount}] is not a valid amount.");
        }

        return self::of((int) round(((float) $amount) * 100), $currency);
    }

    /**
     * Add another amount in the same currency.
     */
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract another amount in the same currency.
     */
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    /**
     * Determine whether the amount is zero.
     */
    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    /**
     * Get the amount in major units as a decimal string.
     */
    public function toMajor(): string
    {
        return number_format($this->amount / 100, 2, '.', '');
    }

    /**
     * Format the amount for display, e.g. "₦1,250.50".
     */
    public function format(string $locale = 'en'): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($this->amount / 100, $this->currency);
    }

    /**
     * Get the JSON representation sent to the frontend.
     *
     * @return array{amount: int, currency: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->format(),
        ];
    }

    /**
     * Guard against mixing currencies.
     */
    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} with {$other->currency}.");
        }
    }
}
