<?php

use App\Enums\BudgetStatus;
use App\Support\Budget\BudgetCalculator;
use App\Support\Period\PeriodResolver;
use Carbon\CarbonImmutable;

it('computes the remaining budget, which may go negative [BR-F03]', function () {
    expect(BudgetCalculator::remaining(800_000, 650_000))->toBe(150_000)
        ->and(BudgetCalculator::remaining(800_000, 900_000))->toBe(-100_000);
});

it('computes the used percentage with one decimal [BR-F03] [BR-A04]', function () {
    expect(BudgetCalculator::percentage(800_000, 650_000))->toBe(81.3)
        ->and(BudgetCalculator::percentage(0, 650_000))->toBeNull();
});

it('classifies the status on exact integers [BR-F04]', function (int $spent, BudgetStatus $status) {
    expect(BudgetCalculator::status(1_000_000, $spent))->toBe($status);
})->with([
    'just below 80%' => [799_999, BudgetStatus::Safe],
    '79,95% displays as 80,0% but is still safe' => [799_500, BudgetStatus::Safe],
    'exactly 80%' => [800_000, BudgetStatus::Warning],
    'just below 100%' => [999_999, BudgetStatus::Warning],
    'exactly 100%' => [1_000_000, BudgetStatus::Over],
    'over' => [1_500_000, BudgetStatus::Over],
]);

it('spreads what is left over the remaining days, rounded down [BR-F06]', function () {
    $period = PeriodResolver::forMonth(2026, 10);

    // 31 Oct is the last day: 150.000 / 1 day.
    expect(BudgetCalculator::dailyAllowance(800_000, 650_000, $period, CarbonImmutable::parse('2026-10-31')))->toBe(150_000)
        // 20 Oct: 12 days left including today → 12.500.
        ->and(BudgetCalculator::dailyAllowance(800_000, 650_000, $period, CarbonImmutable::parse('2026-10-20')))->toBe(12_500)
        // 100.001 / 3 days → 33.333 (floor).
        ->and(BudgetCalculator::dailyAllowance(200_001, 100_000, $period, CarbonImmutable::parse('2026-10-29')))->toBe(33_333)
        // Overspent → 0, never negative.
        ->and(BudgetCalculator::dailyAllowance(800_000, 900_000, $period, CarbonImmutable::parse('2026-10-20')))->toBe(0)
        // Period over → 0.
        ->and(BudgetCalculator::dailyAllowance(800_000, 100_000, $period, CarbonImmutable::parse('2026-11-02')))->toBe(0);
});
