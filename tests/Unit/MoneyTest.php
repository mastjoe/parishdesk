<?php

use App\Support\Money\Money;

test('major amounts are stored as exact minor units', function (string|float $major, int $minor) {
    expect(Money::fromMajor($major, 'ngn'))
        ->amount->toBe($minor)
        ->currency->toBe('NGN');
})->with([
    ['1250.50', 125050],
    [0.1 + 0.2, 30],
    ['19.99', 1999],
    ['0', 0],
]);

test('amounts in the same currency add and subtract', function () {
    $total = Money::of(1000, 'NGN')->add(Money::of(250, 'NGN'))->subtract(Money::of(50, 'NGN'));

    expect($total->amount)->toBe(1200)
        ->and($total->toMajor())->toBe('12.00');
});

test('different currencies cannot be combined', function () {
    Money::of(100, 'NGN')->add(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class, 'Cannot combine NGN with USD.');

test('non numeric amounts are rejected', function () {
    Money::fromMajor('ten', 'NGN');
})->throws(InvalidArgumentException::class);

test('money is formatted with its currency', function () {
    expect(Money::of(125050, 'USD')->format('en_US'))->toBe('$1,250.50')
        ->and(Money::of(125050, 'NGN')->jsonSerialize())->toMatchArray(['amount' => 125050, 'currency' => 'NGN']);
});
