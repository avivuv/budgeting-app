<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Family\FamilyData;

/**
 * Creating a family, the same for the web onboarding page and the REST API.
 */
trait FamilyRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'period_start_day' => ['nullable', 'integer', 'between:1,28'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama keluarga', 'period_start_day' => 'tanggal awal periode'];
    }

    public function toData(): FamilyData
    {
        return new FamilyData(
            name: trim((string) $this->validated('name')),
            periodStartDay: (int) ($this->validated('period_start_day') ?? 1),
        );
    }
}
