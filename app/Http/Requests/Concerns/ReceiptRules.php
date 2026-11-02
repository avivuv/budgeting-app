<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Receipt\ReceiptDraftData;
use App\Enums\ReceiptConfirmMode;
use App\Support\Money\AmountParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by the web and API receipt requests (BR-B12). Format, size, PDF pages,
 * duplicates and quota are checked in ReceiptService (BR-H04, BR-H05, BR-H11).
 *
 * @mixin FormRequest
 */
trait ReceiptRules
{
    use ParsesAmounts;

    /**
     * @return array<string, mixed>
     */
    protected function uploadRules(): array
    {
        // Upper bound only to stop huge bodies early; the exact limit is BR-H11.
        return ['file' => ['required', 'file', 'max:'.((int) config('receipt.max_file_mb') * 1024)]];
    }

    protected function prepareDraft(): void
    {
        $this->parseAmounts(['total']);

        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as $index => $item) {
                if (is_array($item) && is_string($item['total'] ?? null) && $item['total'] !== '') {
                    $items[$index]['total'] = AmountParser::parse($item['total']) ?? $item['total'];
                }
            }
            $this->merge(['items' => $items]);
        }
    }

    /**
     * BR-H09: everything the AI read can be corrected.
     *
     * @return array<string, mixed>
     */
    protected function draftRules(): array
    {
        return [
            'merchant' => ['nullable', 'string', 'max:120'],
            'receipt_date' => ['required', 'date_format:Y-m-d'],
            'total' => ['required', 'integer', 'min:1', 'max:'.AmountParser::MAX],
            'category_id' => ['nullable', 'integer'],
            'wallet_id' => ['nullable', 'integer'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:120'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0.01', 'max:999999'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:'.AmountParser::MAX],
            'items.*.total' => ['required', 'integer', 'min:-'.AmountParser::MAX, 'max:'.AmountParser::MAX],
            'items.*.category_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function confirmRules(): array
    {
        return ['mode' => ['required', Rule::enum(ReceiptConfirmMode::class)]];
    }

    public function toDraft(): ReceiptDraftData
    {
        $validated = $this->validated();

        if (isset($validated['items'])) {
            $validated['items'] = array_map(fn (array $item): array => [...$item, 'qty' => (float) ($item['qty'] ?? 1)], $validated['items']);
        }

        return ReceiptDraftData::from($validated);
    }

    public function mode(): ReceiptConfirmMode
    {
        return ReceiptConfirmMode::from((string) $this->validated('mode'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'file',
            'merchant' => 'toko',
            'receipt_date' => 'tanggal',
            'total' => 'total',
            'category_id' => 'kategori',
            'wallet_id' => 'dompet',
            'items' => 'item',
            'items.*.name' => 'nama item',
            'items.*.qty' => 'jumlah',
            'items.*.total' => 'harga item',
            'mode' => 'cara simpan',
        ];
    }
}
