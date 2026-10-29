<?php

use App\Support\Theme\CustomPalette;

test('a hex colour keeps its hue in oklch', function (string $hex, float $hue) {
    expect(CustomPalette::fromHex($hex)->hue)->toEqualWithDelta($hue, 1.5);
})->with([
    'red' => ['#ff0000', 29.2],
    'green' => ['#00ff00', 142.5],
    'blue' => ['#0000ff', 264.1],
]);

test('readable foregrounds are chosen for light and dark backgrounds', function () {
    $palette = CustomPalette::fromHex('#2f5fb3');

    expect($palette->foregroundFor(0.45))->toBe('oklch(0.985 0 0)')
        ->and($palette->foregroundFor(0.85))->toStartWith('oklch(0.200');
});

test('very light and very dark colours are clamped into a usable range', function (string $hex) {
    $css = CustomPalette::fromHex($hex)->css();

    preg_match('/:root\[data-theme="custom"\]\{--primary:oklch\(([\d.]+)/', $css, $light);
    preg_match('/:root\.dark\[data-theme="custom"\]\{--primary:oklch\(([\d.]+)/', $css, $dark);

    expect((float) $light[1])->toBeBetween(0.38, 0.62)
        ->and((float) $dark[1])->toBeBetween(0.68, 0.82);
})->with(['#fffde7', '#0a0a0a']);

test('invalid colours are rejected', function (string $hex) {
    CustomPalette::fromHex($hex);
})->throws(InvalidArgumentException::class)->with(['red', '#fff', '#12345g', '#123456;}']);
