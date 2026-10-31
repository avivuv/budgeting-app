<?php

use App\Support\Math\Percentage;
use App\Support\Money\MoneyFormatter;

it('formats rupiah for display [BR-A06]', function (int $amount, string $expected) {
    expect(MoneyFormatter::format($amount))->toBe($expected);
})->with([
    [0, 'Rp 0'],
    [500, 'Rp 500'],
    [50_000, 'Rp 50.000'],
    [1_250_000, 'Rp 1.250.000'],
    [-50_000, '-Rp 50.000'],
]);

it('formats compact amounts for charts [BR-A06]', function (int $amount, string $expected) {
    expect(MoneyFormatter::short($amount))->toBe($expected);
})->with([
    [500, '500'],
    [850_000, '850 rb'],
    [1_000_000, '1 jt'],
    [1_250_000, '1,25 jt'],
    [1_255_000, '1,26 jt'],
    [2_500_000_000, '2,5 M'],
    [-1_500_000, '-1,5 jt'],
]);

it('rounds percentages to one decimal half up [BR-A04]', function (int $part, int $whole, float $expected) {
    expect(Percentage::of($part, $whole))->toBe($expected);
})->with([
    [650_000, 800_000, 81.3],     // 81.25 => 81.3
    [650_000, 2_287_500, 28.4],
    [7_995, 10_000, 80.0],        // 79.95 => 80.0
    [1, 3, 33.3],
    [0, 100, 0.0],
    [150, 100, 150.0],
]);

it('returns null percentage for a zero whole', function () {
    expect(Percentage::of(10, 0))->toBeNull();
});

it('calculates period-over-period change [BR-A05]', function (int $current, int $previous, ?float $expected) {
    expect(Percentage::change($current, $previous))->toBe($expected);
})->with([
    [1_250_000, 1_000_000, 25.0],
    [750_000, 1_000_000, -25.0],
    [1_000_000, 1_000_000, 0.0],
    [100, 0, null],
]);
