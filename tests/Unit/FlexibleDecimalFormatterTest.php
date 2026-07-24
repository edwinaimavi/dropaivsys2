<?php

use App\Support\FlexibleDecimalFormatter;

it('muestra al menos dos decimales', function (string $value, string $expected) {
    expect(FlexibleDecimalFormatter::format($value))->toBe($expected);
})->with([
    ['22', '22.00'],
    ['22.5', '22.50'],
    ['22.50', '22.50'],
    ['1100', '1100.00'],
]);

it('conserva hasta seis decimales significativos', function (string $value, string $expected) {
    expect(FlexibleDecimalFormatter::format($value))->toBe($expected);
})->with([
    ['22.123', '22.123'],
    ['22.1234', '22.1234'],
    ['22.123456', '22.123456'],
    ['0.833551', '0.833551'],
]);
