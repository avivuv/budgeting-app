<?php

use App\Support\Money\AmountParser;

it('parses rupiah amounts [BR-A03]', function (string $input, int $expected) {
    expect(AmountParser::parse($input))->toBe($expected);
})->with([
    ['50000', 50_000],
    ['50.000', 50_000],
    ['1.250.000', 1_250_000],
    ['1,250,000', 1_250_000],
    ['1.500.000,00', 1_500_000],
    ['Rp 50.000', 50_000],
    ['rp50000', 50_000],
    ['50rb', 50_000],
    ['50 rb', 50_000],
    ['50ribu', 50_000],
    ['50k', 50_000],
    ['50K', 50_000],
    ['1,5rb', 1_500],
    ['1,5jt', 1_500_000],
    ['1.5jt', 1_500_000],
    ['1,5 juta', 1_500_000],
    ['2jt', 2_000_000],
    ['2jt300', 2_300_000],
    ['1jt250', 1_250_000],
    ['2jt5', 2_500_000],
    ['1.250rb', 1_250_000],
    ['1m', 1_000_000_000],
    ['2,5 miliar', 2_500_000_000],
    ['999.999.999.999', 999_999_999_999],
]);

it('rejects invalid amounts [BR-A02] [BR-A03]', function (?string $input) {
    expect(AmountParser::parse($input))->toBeNull();
})->with([
    null,
    '',
    '   ',
    '0',
    '0rb',
    '50,5',
    '50.5',
    '1.2345rb',
    'abc',
    '-50000',
    '50.00.0',
    '1.000.000.000.000',
    '1000m',
    'rb',
]);
