<?php

use App\Support\Receipt\ReceiptSplitter;

it('shares discount and tax proportionally and adds up to the total exactly [BR-H02] [INV-09]', function () {
    // Groceries 60.000 + snacks 40.000 = 100.000; paid 95.000 after a 5.000 discount.
    $parts = ReceiptSplitter::split([1 => 60_000, 2 => 40_000], 95_000, 1);

    expect($parts)->toBe([1 => 57_000, 2 => 38_000])
        ->and(array_sum($parts))->toBe(95_000);
});

it('gives the rounding leftover to the largest subtotal [BR-H02] [INV-09]', function () {
    // 100.000 over three equal-ish groups: 33.333 each, 1 rupiah left over.
    $parts = ReceiptSplitter::split([1 => 10_000, 2 => 20_000, 3 => 10_000], 100_001, 1);

    expect($parts)->toBe([1 => 25_000, 2 => 50_001, 3 => 25_000])
        ->and(array_sum($parts))->toBe(100_001);

    $parts = ReceiptSplitter::split([4 => 1, 5 => 1, 6 => 1], 100, 4);
    expect($parts)->toBe([4 => 34, 5 => 33, 6 => 33])
        ->and(array_sum($parts))->toBe(100);
});

it('ignores groups that are not positive and falls back when nothing is [BR-H02]', function () {
    // A discount line in its own category (-5.000) gets no transaction.
    expect(ReceiptSplitter::split([1 => 50_000, 2 => -5_000], 45_000, 1))->toBe([1 => 45_000])
        ->and(ReceiptSplitter::split([1 => 0, 2 => -1_000], 30_000, 7))->toBe([7 => 30_000])
        ->and(ReceiptSplitter::split([], 30_000, 7))->toBe([7 => 30_000])
        ->and(ReceiptSplitter::split([1 => 10_000], 0, 1))->toBe([]);
});

it('never creates a zero amount for a tiny category [BR-H02] [INV-09]', function () {
    $parts = ReceiptSplitter::split([1 => 1_000_000, 2 => 1], 1_000, 1);

    expect($parts)->toBe([1 => 1_000])
        ->and(array_sum($parts))->toBe(1_000);
});

it('stays exact for very large amounts [BR-H02] [INV-09]', function () {
    $parts = ReceiptSplitter::split([1 => 600_000_000_000, 2 => 399_999_999_999], 999_999_999_999, 1);

    expect(array_sum($parts))->toBe(999_999_999_999)
        ->and($parts[2])->toBe(399_999_999_999);
});

it('always adds up to the total for random receipts [INV-09]', function () {
    mt_srand(42);

    for ($run = 0; $run < 200; $run++) {
        $subtotals = [];
        for ($i = 1; $i <= mt_rand(1, 6); $i++) {
            $subtotals[$i] = mt_rand(-5_000, 500_000);
        }
        $total = mt_rand(1, 2_000_000);

        $parts = ReceiptSplitter::split($subtotals, $total, 1);

        expect(array_sum($parts))->toBe($total);
        foreach ($parts as $amount) {
            expect($amount)->toBeGreaterThan(0);
        }
    }
});
