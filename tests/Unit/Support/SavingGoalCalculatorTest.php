<?php

use App\Support\Period\PeriodResolver;
use App\Support\Savings\SavingGoalCalculator;

it('computes remaining and percentage, never below zero [BR-K03]', function () {
    expect(SavingGoalCalculator::remaining(10_000_000, 2_500_000))->toBe(7_500_000)
        ->and(SavingGoalCalculator::remaining(10_000_000, 12_000_000))->toBe(0)
        ->and(SavingGoalCalculator::percentage(10_000_000, 2_500_000))->toBe(25.0)
        ->and(SavingGoalCalculator::percentage(10_000_000, -500_000))->toBe(0.0)
        ->and(SavingGoalCalculator::achieved(10_000_000, 10_000_000))->toBeTrue()
        ->and(SavingGoalCalculator::achieved(10_000_000, 9_999_999))->toBeFalse();
});

it('spreads what is left over the periods up to the deadline, rounded up [BR-K04]', function () {
    $november = PeriodResolver::forMonth(2026, 11);

    // Nov … Apr = 6 periods: 7.500.000 / 6 = 1.250.000.
    expect(SavingGoalCalculator::monthlyNeeded(10_000_000, 2_500_000, $november, PeriodResolver::forMonth(2027, 4)))->toBe(1_250_000)
        // 1.000.001 over 3 periods → 333.334 (ceil).
        ->and(SavingGoalCalculator::monthlyNeeded(1_000_001, 0, $november, PeriodResolver::forMonth(2027, 1)))->toBe(333_334)
        // Deadline in the current period: everything now.
        ->and(SavingGoalCalculator::monthlyNeeded(500_000, 0, $november, $november))->toBe(500_000)
        // No deadline, deadline passed, or already reached → not computed.
        ->and(SavingGoalCalculator::monthlyNeeded(500_000, 0, $november, null))->toBeNull()
        ->and(SavingGoalCalculator::monthlyNeeded(500_000, 0, $november, PeriodResolver::forMonth(2026, 10)))->toBeNull()
        ->and(SavingGoalCalculator::monthlyNeeded(500_000, 600_000, $november, PeriodResolver::forMonth(2027, 4)))->toBeNull();
});
