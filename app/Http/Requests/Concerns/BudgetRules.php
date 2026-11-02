<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Money\AmountParser;

/**
 * Setting a budget, the same for the web page and the REST API.
 * Category type (BR-F01) is checked in BudgetService.
 */
trait BudgetRules
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $this->parseAmounts(['amount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'integer', 'min:1', 'max:'.AmountParser::MAX],
            'period' => ['nullable', 'date_format:Y-m'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['category_id' => 'kategori', 'amount' => 'nominal budget', 'period' => 'periode'];
    }
}
