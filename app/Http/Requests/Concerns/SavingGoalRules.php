<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Savings\SavingGoalData;
use App\Support\Money\AmountParser;

/**
 * Creating/updating a savings goal, the same for the web page and the REST API.
 */
trait SavingGoalRules
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $this->parseAmounts(['target_amount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'target_amount' => ['required', 'integer', 'min:1', 'max:'.AmountParser::MAX],
            'wallet_id' => ['nullable', 'integer', 'min:1'],
            'deadline' => ['nullable', 'date_format:Y-m-d'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama', 'target_amount' => 'target', 'wallet_id' => 'dompet', 'deadline' => 'tenggat'];
    }

    public function toData(): SavingGoalData
    {
        return SavingGoalData::from($this->validated());
    }
}
