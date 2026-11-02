<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ParsesAmounts;
use App\Support\Money\AmountParser;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/budgets: set several budgets of one period at once.
 */
class BudgetBulkRequest extends FormRequest
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $budgets = $this->input('budgets');

        if (is_array($budgets)) {
            $this->merge(['budgets' => array_map(function (mixed $row): mixed {
                if (is_array($row) && is_string($row['amount'] ?? null)) {
                    $row['amount'] = AmountParser::parse($row['amount']) ?? $row['amount'];
                }

                return $row;
            }, $budgets)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'date_format:Y-m'],
            'budgets' => ['required', 'array', 'min:1', 'max:100'],
            'budgets.*.category_id' => ['required', 'integer', 'min:1', 'distinct'],
            'budgets.*.amount' => ['required', 'integer', 'min:1', 'max:'.AmountParser::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['budgets' => 'budget', 'budgets.*.category_id' => 'kategori', 'budgets.*.amount' => 'nominal budget', 'period' => 'periode'];
    }

    /**
     * @return list<array{category_id: int, amount: int}>
     */
    public function budgets(): array
    {
        /** @var list<array{category_id: int|string, amount: int|string}> $rows */
        $rows = $this->validated('budgets');

        return array_map(fn (array $row): array => ['category_id' => (int) $row['category_id'], 'amount' => (int) $row['amount']], $rows);
    }
}
