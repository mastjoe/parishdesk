<?php

use App\Support\Numbering\NumberGenerator;

test('numbers are sequential and formatted', function () {
    $this->travelTo(now()->setDate(2026, 10, 29));
    $generator = app(NumberGenerator::class);

    expect($generator->next('members', 'MEM-{Y}-{seq:4}'))->toBe('MEM-2026-0001')
        ->and($generator->next('members', 'MEM-{Y}-{seq:4}'))->toBe('MEM-2026-0002')
        ->and($generator->next('receipts', 'RCPT-{seq:6}'))->toBe('RCPT-000001');
});

test('the stored format wins over the default once a sequence exists', function () {
    $generator = app(NumberGenerator::class);
    $generator->next('members', 'MEM-{seq:3}');

    expect($generator->next('members', 'IGNORED-{seq}'))->toBe('MEM-002');
});

test('yearly sequences restart in a new year', function () {
    $generator = app(NumberGenerator::class);

    $this->travelTo(now()->setDate(2026, 12, 31));
    $generator->next('baptisms', 'BAP/{Y}/{seq:3}', resetsYearly: true);
    expect($generator->next('baptisms', 'BAP/{Y}/{seq:3}', resetsYearly: true))->toBe('BAP/2026/002');

    $this->travelTo(now()->setDate(2027, 1, 1));
    expect($generator->next('baptisms', 'BAP/{Y}/{seq:3}', resetsYearly: true))->toBe('BAP/2027/001');
});
