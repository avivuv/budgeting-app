<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Family;

use App\DTOs\Family\FamilyData;
use Illuminate\Foundation\Http\FormRequest;

class FamilyRequest extends FormRequest
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
