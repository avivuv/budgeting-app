<?php

use App\Enums\CategoryType;
use App\Enums\TransactionType;
use App\Support\Parsing\ParsedLine;
use App\Support\Parsing\ParserVocabulary;
use App\Support\Parsing\TransactionTextParser;
use Carbon\CarbonImmutable;

/*
| Free-text parser (docs/05-telegram-bot.md §3.1). Frozen "today": Saturday 31 Oct 2026.
| Wallets: 1 Tunai, 2 BCA, 3 GoPay, 4 Kartu Kredit BNI.
| Categories: 10 Makanan & Minuman, 11 Transportasi, 12 Belanja Harian,
| 13 Tagihan & Utilitas (expense); 20 Gaji, 21 Bonus (income).
*/

function parserVocabulary(): ParserVocabulary
{
    return new ParserVocabulary(
        wallets: [1 => 'Tunai', 2 => 'BCA', 3 => 'GoPay', 4 => 'Kartu Kredit BNI'],
        categories: [
            ['id' => 10, 'name' => 'Makanan & Minuman', 'type' => CategoryType::Expense, 'keywords' => ['makan', 'makan siang', 'kopi', 'sarapan', 'jajan']],
            ['id' => 11, 'name' => 'Transportasi', 'type' => CategoryType::Expense, 'keywords' => ['bensin', 'parkir', 'ojek', 'tol']],
            ['id' => 12, 'name' => 'Belanja Harian', 'type' => CategoryType::Expense, 'keywords' => ['sayur', 'indomaret', 'beras']],
            ['id' => 13, 'name' => 'Tagihan & Utilitas', 'type' => CategoryType::Expense, 'keywords' => ['listrik', 'internet', 'pulsa', 'pln']],
            ['id' => 20, 'name' => 'Gaji', 'type' => CategoryType::Income, 'keywords' => ['gaji', 'thr']],
            ['id' => 21, 'name' => 'Bonus', 'type' => CategoryType::Income, 'keywords' => ['bonus']],
        ],
    );
}

function parseOne(string $text): ParsedLine
{
    return TransactionTextParser::parseLine($text, CarbonImmutable::parse('2026-10-31 15:00', 'Asia/Jakarta'), parserVocabulary());
}

it('parses a line into transaction fields', function (string $text, array $expected) {
    $line = parseOne($text);

    $actual = [
        'type' => $line->type->value,
        'amount' => $line->amount,
        'date' => $line->date->toDateString(),
        'wallet' => $line->walletId,
        'to' => $line->toWalletId,
        'category' => $line->categoryId,
        'source' => $line->categorySource,
        'note' => $line->note,
        'tags' => $line->tags,
        'missing' => $line->missing,
    ];

    expect(array_intersect_key($actual, $expected))->toEqual($expected);
})->with([
    // Amounts (BR-A03)
    'rb' => ['bensin 50rb', ['type' => 'expense', 'amount' => 50_000, 'category' => 11, 'note' => 'bensin', 'missing' => []]],
    'k' => ['bensin 50k', ['amount' => 50_000]],
    'separate unit' => ['bensin 50 rb', ['amount' => 50_000, 'note' => 'bensin']],
    'thousand dots' => ['makan siang 35.000', ['amount' => 35_000, 'category' => 10, 'note' => 'makan siang']],
    'decimal jt' => ['kopi 1,5jt', ['amount' => 1_500_000, 'category' => 10]],
    'juta word' => ['listrik 1.5 juta', ['amount' => 1_500_000, 'category' => 13]],
    'jt with rest' => ['beras 2jt300', ['amount' => 2_300_000, 'category' => 12]],
    'rp prefix' => ['parkir Rp50.000', ['amount' => 50_000, 'note' => 'parkir']],
    'uppercase' => ['BENSIN 50RB', ['amount' => 50_000, 'category' => 11]],
    'cents rejected' => ['kopi 50,5', ['amount' => null, 'missing' => ['amount']]],
    'no amount' => ['bensin', ['amount' => null, 'missing' => ['amount']]],
    'small number is not the amount' => ['beli 2 kopi 30rb', ['amount' => 30_000, 'note' => 'beli 2 kopi', 'category' => 10]],
    'small amount as fallback' => ['permen 500', ['amount' => 500]],
    'extra spaces' => ['  bensin   50rb  ', ['amount' => 50_000, 'note' => 'bensin']],

    // Type
    'plus means income' => ['+ gaji 8jt', ['type' => 'income', 'amount' => 8_000_000, 'category' => 20]],
    'plus glued' => ['+8jt gaji', ['type' => 'income', 'amount' => 8_000_000]],
    'income keyword' => ['gaji 8jt', ['type' => 'income', 'category' => 20, 'source' => 'keyword']],
    'income keyword dapat' => ['dapat bonus 1jt', ['type' => 'income', 'category' => 21]],
    'income category keyword with plus' => ['+ thr 5jt', ['type' => 'income', 'category' => 20]],
    'expense by default' => ['ojek 15rb', ['type' => 'expense']],

    // Transfers
    'tf with ke' => ['tf 500rb bca ke gopay', ['type' => 'transfer', 'amount' => 500_000, 'wallet' => 2, 'to' => 3, 'category' => null, 'missing' => []]],
    'transfer with arrow' => ['transfer 1jt bca>tunai', ['type' => 'transfer', 'wallet' => 2, 'to' => 1]],
    'transfer by names' => ['tf 200rb bca gopay', ['wallet' => 2, 'to' => 3]],
    'transfer without target' => ['tf 200rb bca', ['type' => 'transfer', 'wallet' => 2, 'to' => null, 'missing' => ['to_wallet_id']]],
    'pindah keyword' => ['pindah 300rb @tunai ke bca', ['type' => 'transfer', 'wallet' => 1, 'to' => 2]],

    // Wallets
    'at sign' => ['bensin 50rb @gopay', ['wallet' => 3, 'note' => 'bensin']],
    'pakai' => ['bensin 50rb pakai bca', ['wallet' => 2, 'note' => 'bensin']],
    'pake' => ['bensin 50rb pake tunai', ['wallet' => 1]],
    'via' => ['parkir 5rb via gopay', ['wallet' => 3]],
    'plain wallet name' => ['bensin 50rb bca', ['wallet' => 2, 'note' => 'bensin']],
    'wallet name prefix' => ['belanja 2jt @kartu', ['wallet' => 4]],
    'wallet name contains' => ['bensin 50rb @bni', ['wallet' => 4]],
    'unknown wallet' => ['bensin 50rb @ovo', ['wallet' => null]],
    'no wallet given' => ['bensin 50rb', ['wallet' => null]],

    // Dates (BR-B06, BR-D03)
    'today by default' => ['makan 35rb', ['date' => '2026-10-31']],
    'kemarin' => ['makan 35rb kemarin', ['date' => '2026-10-30', 'note' => 'makan']],
    'kmrn' => ['makan 35rb kmrn', ['date' => '2026-10-30']],
    'n hari lalu' => ['makan 35rb 2 hari lalu', ['date' => '2026-10-29', 'amount' => 35_000, 'note' => 'makan']],
    'n hari yang lalu' => ['makan 35rb 3 hari yang lalu', ['date' => '2026-10-28', 'note' => 'makan']],
    'tgl' => ['makan 35rb tgl 25', ['date' => '2026-10-25', 'amount' => 35_000]],
    'tanggal' => ['makan 35rb tanggal 5', ['date' => '2026-10-05']],
    'day after today is last month' => ['bensin 50rb tgl 31', ['date' => '2026-10-31']],
    'day/month' => ['kopi 20rb 25/10', ['date' => '2026-10-25', 'amount' => 20_000]],
    'future day/month is last year' => ['kopi 20rb 25/12', ['date' => '2025-12-25']],
    'day/month/year' => ['kopi 20rb 1/1/2026', ['date' => '2026-01-01']],
    'two-digit year' => ['kopi 20rb 17/8/25', ['date' => '2025-08-17']],
    'invalid date stays text' => ['kopi 20rb 31/2', ['date' => '2026-10-31', 'note' => 'kopi 31/2']],
    'hari ini' => ['kopi 20rb hari ini', ['date' => '2026-10-31', 'note' => 'kopi']],

    // Tags
    'tag' => ['makan 50rb #kantor', ['tags' => ['kantor'], 'note' => 'makan']],
    'tags lowercased and unique' => ['makan 50rb #Kantor #liburan #kantor', ['tags' => ['kantor', 'liburan']]],

    // Categories (BR-E05)
    'explicit category' => ['!transportasi 5rb', ['category' => 11, 'source' => 'explicit', 'note' => null]],
    'explicit by keyword' => ['!makan 25rb nasi padang', ['category' => 10, 'source' => 'explicit', 'note' => 'nasi padang']],
    'explicit by prefix' => ['!tagihan 200rb', ['category' => 13]],
    'explicit with dash' => ['!belanja-harian 100rb', ['category' => 12]],
    'explicit beats keyword' => ['!belanja bensin 50rb', ['category' => 12, 'source' => 'explicit']],
    'whole words only' => ['beli makanan kucing 50rb', ['category' => null, 'missing' => ['category_id']]],
    'no keyword' => ['nasi padang 25rb', ['category' => null, 'missing' => ['category_id']]],
    'multi-word keyword' => ['makan siang kantor 35rb', ['category' => 10]],
    'keyword mid sentence' => ['bayar tol 20rb', ['category' => 11]],
    'note keeps unmatched words' => ['sayur 15rb indomaret', ['category' => 12, 'note' => 'sayur indomaret']],

    // Everything together
    'combined' => ['ojek 15rb @tunai kemarin #kantor', ['type' => 'expense', 'amount' => 15_000, 'wallet' => 1, 'date' => '2026-10-30', 'tags' => ['kantor'], 'category' => 11, 'note' => 'ojek']],
]);

it('reads one transaction per line and skips empty lines', function () {
    $lines = TransactionTextParser::parse(
        "bensin 50rb\nparkir 5rb\n\n  \nmakan siang 35rb @gopay",
        CarbonImmutable::parse('2026-10-31', 'Asia/Jakarta'),
        parserVocabulary(),
    );

    expect($lines)->toHaveCount(3)
        ->and(array_map(fn (ParsedLine $l) => $l->amount, $lines))->toBe([50_000, 5_000, 35_000])
        ->and($lines[2]->walletId)->toBe(3);
});

it('reads at most 20 lines', function () {
    $lines = TransactionTextParser::parse(
        implode("\n", array_fill(0, 30, 'kopi 10rb')),
        CarbonImmutable::parse('2026-10-31', 'Asia/Jakarta'),
        parserVocabulary(),
    );

    expect($lines)->toHaveCount(TransactionTextParser::MAX_LINES);
});

it('can fill a category from history afterwards [BR-E05]', function () {
    $line = parseOne('nasi padang 25rb')->withCategory(10, ParsedLine::CATEGORY_HISTORY);

    expect($line->categoryId)->toBe(10)
        ->and($line->categorySource)->toBe('history')
        ->and($line->missing)->toBe([])
        ->and($line->type)->toBe(TransactionType::Expense);
});
