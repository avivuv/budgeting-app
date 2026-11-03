<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Budget;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /budgets and POST /budgets/copy: the period to read or copy into.
 */
class BudgetPeriodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['period' => ['nullable', 'string', 'date_format:Y-m']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['period.date_format' => 'Format periode tidak valid.'];
    }

    public function period(): ?string
    {
        $period = $this->validated('period');

        return is_string($period) ? $period : null;
    }
}
