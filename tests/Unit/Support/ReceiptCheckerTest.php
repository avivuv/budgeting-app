<?php

use App\DTOs\Receipt\ParsedReceiptData;
use App\Enums\ReceiptDocumentType;
use App\Support\Receipt\CategoryMatcher;
use App\Support\Receipt\ReceiptChecker;
use Carbon\CarbonImmutable;

it('flags a total that differs more than 2% from items, discount and tax [BR-H03]', function () {
    // 85.000 items + 2.500 tax = 87.500 → matches.
    expect(ReceiptChecker::totalMismatch([37_000, 48_000], 0, 2_500, 87_500))->toBeFalse()
        // 100.000 − 5.000 discount = 95.000; paid 96.900 is within 2% (1.938).
        ->and(ReceiptChecker::totalMismatch([100_000], 5_000, 0, 96_900))->toBeFalse()
        // 2.000 off on 97.000 is more than 2% (1.940).
        ->and(ReceiptChecker::totalMismatch([100_000], 5_000, 0, 97_000))->toBeTrue()
        // Transfers have no items: nothing to compare.
        ->and(ReceiptChecker::totalMismatch([], 0, 0, 250_000))->toBeFalse();

    expect(ReceiptChecker::lowerConfidence(0.9))->toBe(0.45)
        ->and(ReceiptChecker::lowerConfidence(null))->toBe(0.5);
});

it('replaces unreadable, future and too old dates with today [BR-H06]', function () {
    $today = CarbonImmutable::parse('2026-10-31 15:00', 'Asia/Jakarta');

    [$date, $replaced] = ReceiptChecker::date('2026-10-30', $today);
    expect($date->toDateString())->toBe('2026-10-30')->and($replaced)->toBeFalse();

    [$date, $replaced] = ReceiptChecker::date('2025-10-31', $today);
    expect($date->toDateString())->toBe('2025-10-31')->and($replaced)->toBeFalse();

    foreach ([null, '31/10/26', '2026-02-30', '2026-11-01', '2025-10-30'] as $read) {
        [$date, $replaced] = ReceiptChecker::date($read, $today);

        expect($date->toDateString())->toBe('2026-10-31')->and($replaced)->toBeTrue();
    }
});

it('maps AI category names and item names to family categories', function () {
    $matcher = new CategoryMatcher([
        ['id' => 1, 'name' => 'Belanja Harian', 'keywords' => ['indomaret', 'susu', 'beras']],
        ['id' => 2, 'name' => 'Transportasi', 'keywords' => ['bensin', 'pertalite', 'grab']],
    ]);

    expect($matcher->match('belanja  harian'))->toBe(1)
        ->and($matcher->match('Susu UHT 1L'))->toBe(1)
        ->and($matcher->match('PERTALITE 10L'))->toBe(2)
        // Whole words only: "susuk" is not "susu".
        ->and($matcher->match('susuk'))->toBeNull()
        ->and($matcher->match('Hiburan'))->toBeNull()
        ->and($matcher->match(null))->toBeNull();
});

it('reads the AI output into whole rupiah and tolerates bad values [BR-H01]', function () {
    $parsed = ParsedReceiptData::fromOutput([
        'is_receipt' => true,
        'document_type' => 'ewallet',
        'merchant' => '  Kopi Kenangan ',
        'date' => '2026-10-30',
        'items' => [
            ['name' => 'Es Kopi Susu', 'qty' => '2', 'unit_price' => 18000, 'total' => '36000.4', 'category' => 'Makanan & Minuman'],
            ['name' => '', 'total' => 1000],
            ['name' => 'Tanpa harga'],
            'garbage',
        ],
        'discount' => -6_000,
        'tax' => null,
        'total' => 30_000,
        'confidence' => 1.7,
    ], 'gemini-test');

    expect($parsed->isReceipt)->toBeTrue()
        ->and($parsed->documentType)->toBe(ReceiptDocumentType::Ewallet)
        ->and($parsed->merchant)->toBe('Kopi Kenangan')
        ->and($parsed->total)->toBe(30_000)
        ->and($parsed->discount)->toBe(6_000)
        ->and($parsed->tax)->toBe(0)
        ->and($parsed->confidence)->toBe(1.0)
        ->and($parsed->items)->toHaveCount(1)
        ->and($parsed->items[0]->qty)->toBe(2.0)
        ->and($parsed->items[0]->total)->toBe(36_000);

    expect(ParsedReceiptData::fromOutput(['is_receipt' => false])->isReceipt)->toBeFalse()
        ->and(ParsedReceiptData::fromOutput(['is_receipt' => true, 'total' => 0])->isReceipt)->toBeFalse()
        ->and(ParsedReceiptData::fromOutput(['is_receipt' => true, 'total' => 'abc'])->isReceipt)->toBeFalse();
});
