<?php

use App\Support\Period\PeriodResolver;
use Carbon\CarbonImmutable;

it('uses calendar months when the period starts on day 1 [BR-D01]', function () {
    $period = PeriodResolver::forMonth(2026, 2, 1);

    expect($period->start->toDateString())->toBe('2026-02-01')
        ->and($period->end->toDateString())->toBe('2026-02-28')
        ->and($period->key())->toBe('2026-02');
});

it('labels a payday period by the month of its end date [BR-D01] [BR-D02]', function () {
    $period = PeriodResolver::forMonth(2026, 11, 25);

    expect($period->start->toDateString())->toBe('2026-10-25')
        ->and($period->end->toDateString())->toBe('2026-11-24');
});

it('crosses the year boundary', function () {
    $period = PeriodResolver::forMonth(2027, 1, 25);

    expect($period->start->toDateString())->toBe('2026-12-25')
        ->and($period->end->toDateString())->toBe('2027-01-24');
});

it('finds the period containing a date [BR-D01]', function (string $date, int $startDay, string $key) {
    expect(PeriodResolver::containing(CarbonImmutable::parse($date), $startDay)->key())->toBe($key);
})->with([
    ['2026-10-31', 1, '2026-10'],
    ['2026-10-24', 25, '2026-10'],
    ['2026-10-25', 25, '2026-11'],
    ['2026-12-28', 25, '2027-01'],
    ['2028-02-29', 1, '2028-02'],
    ['2026-03-28', 28, '2026-04'],
]);

it('treats both ends as inclusive [BR-D04]', function () {
    $period = PeriodResolver::forMonth(2026, 11, 25);

    expect($period->contains(CarbonImmutable::parse('2026-10-25')))->toBeTrue()
        ->and($period->contains(CarbonImmutable::parse('2026-11-24 23:59')))->toBeTrue()
        ->and($period->contains(CarbonImmutable::parse('2026-11-25')))->toBeFalse();
});

it('counts remaining days including today [BR-F06]', function () {
    $period = PeriodResolver::forMonth(2026, 10, 1);

    expect($period->daysRemaining(CarbonImmutable::parse('2026-10-31')))->toBe(1)
        ->and($period->daysRemaining(CarbonImmutable::parse('2026-10-01')))->toBe(31)
        ->and($period->daysRemaining(CarbonImmutable::parse('2026-11-01')))->toBe(0);
});

it('rejects start days outside 1..28', function () {
    PeriodResolver::forMonth(2026, 1, 29);
})->throws(InvalidArgumentException::class);
