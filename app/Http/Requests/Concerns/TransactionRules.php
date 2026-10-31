<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\TransactionType;
use App\Support\Money\AmountParser;
use Illuminate\Validation\Rule;

/**
 * Shared by the web and API transaction requests so both validate identically (BR-B12).
 * Business rules (category type, transfers, archived wallets, date range) are
 * enforced in TransactionService.
 */
trait TransactionRules
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $this->parseAmounts(['amount']);

        $tags = $this->input('tags');
        if (is_string($tags)) {
            $this->merge(['tags' => array_values(array_filter(array_map('trim', explode(',', $tags))))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function transactionRules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:'.AmountParser::MAX],
            'date' => ['required', 'date_format:Y-m-d'],
            'wallet_id' => ['required', 'integer'],
            'to_wallet_id' => ['nullable', 'integer', Rule::requiredIf(fn () => $this->input('type') === TransactionType::Transfer->value)],
            'category_id' => ['nullable', 'integer', Rule::requiredIf(fn () => in_array($this->input('type'), [TransactionType::Income->value, TransactionType::Expense->value], true))],
            'note' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'jenis',
            'amount' => 'nominal',
            'date' => 'tanggal',
            'wallet_id' => 'dompet',
            'to_wallet_id' => 'dompet tujuan',
            'category_id' => 'kategori',
            'note' => 'catatan',
            'tags' => 'tag',
        ];
    }
}
