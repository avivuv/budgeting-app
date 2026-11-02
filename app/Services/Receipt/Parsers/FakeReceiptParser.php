<?php

declare(strict_types=1);

namespace App\Services\Receipt\Parsers;

use App\DTOs\Receipt\ParsedReceiptData;
use App\DTOs\Receipt\ReceiptParseContextData;
use Throwable;

/**
 * Development and test parser (RECEIPT_PARSER=fake), no API key needed.
 * Without queued responses it "reads" a minimarket receipt whose amounts
 * depend on the file, so two different uploads are not flagged as duplicates.
 */
class FakeReceiptParser implements ReceiptParserInterface
{
    public const string MODEL = 'fake';

    /** @var list<ParsedReceiptData|Throwable> */
    private array $responses = [];

    /** @var list<array{path: string, mime: string, context: ReceiptParseContextData}> */
    private array $calls = [];

    /**
     * Queue the next readings (or failures), used in tests.
     */
    public function respondWith(ParsedReceiptData|Throwable ...$responses): static
    {
        array_push($this->responses, ...$responses);

        return $this;
    }

    /**
     * @return list<array{path: string, mime: string, context: ReceiptParseContextData}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    public function parse(string $filePath, string $mimeType, ReceiptParseContextData $context): ParsedReceiptData
    {
        $this->calls[] = ['path' => $filePath, 'mime' => $mimeType, 'context' => $context];

        $next = array_shift($this->responses);

        if ($next instanceof Throwable) {
            throw $next;
        }

        if ($next instanceof ParsedReceiptData) {
            return $next;
        }

        $output = self::sample($context, (string) hash_file('sha256', $filePath));

        return ParsedReceiptData::fromOutput($output, self::MODEL, $output);
    }

    /**
     * @return array<string, mixed>
     */
    public static function sample(ReceiptParseContextData $context, string $seed = '0'): array
    {
        $groceries = self::pick($context->categories, ['Belanja Harian', 'Belanja']);
        $snack = self::pick($context->categories, ['Makanan & Minuman', 'Belanja Harian']);
        $water = 3_000 + (hexdec(substr($seed, 0, 2)) % 10) * 500;

        $items = [
            ['name' => 'Susu UHT 1L', 'qty' => 2, 'unit_price' => 18_500, 'total' => 37_000, 'category' => $groceries],
            ['name' => 'Roti Tawar', 'qty' => 1, 'unit_price' => 15_500, 'total' => 15_500, 'category' => $groceries],
            ['name' => 'Minyak Goreng 1L', 'qty' => 1, 'unit_price' => 21_000, 'total' => 21_000, 'category' => $groceries],
            ['name' => 'Keripik Kentang', 'qty' => 1, 'unit_price' => 11_500, 'total' => 11_500, 'category' => $snack],
            ['name' => 'Air Mineral 600ml', 'qty' => 1, 'unit_price' => $water, 'total' => $water, 'category' => $snack],
        ];
        $subtotal = array_sum(array_column($items, 'total'));

        return [
            'is_receipt' => true,
            'document_type' => 'receipt',
            'merchant' => 'Indomaret',
            'date' => $context->today,
            'currency' => 'IDR',
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => 0,
            'tax' => 0,
            'total' => $subtotal,
            'payment_method' => 'QRIS',
            'suggested_wallet' => null,
            'suggested_category' => $groceries,
            'confidence' => 0.9,
        ];
    }

    /**
     * @param  list<string>  $names
     * @param  list<string>  $preferred
     */
    private static function pick(array $names, array $preferred): ?string
    {
        foreach ($preferred as $name) {
            if (in_array($name, $names, true)) {
                return $name;
            }
        }

        return $names[0] ?? null;
    }
}
