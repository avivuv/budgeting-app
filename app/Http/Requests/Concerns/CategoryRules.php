<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Category\CategoryData;
use App\Enums\CategoryType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Category form, shared by the API and the legacy web request so both validate
 * identically (docs/api-contracts/categories.md). Keywords may come as a list
 * or as comma-separated text.
 *
 * @mixin FormRequest
 */
trait CategoryRules
{
    protected function prepareForValidation(): void
    {
        $keywords = $this->input('keywords');

        if (is_string($keywords)) {
            $this->merge(['keywords' => array_values(array_filter(array_map('trim', explode(',', $keywords))))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CategoryType::class)],
            'name' => ['required', 'string', 'max:50'],
            'parent_id' => ['nullable', 'integer'],
            'icon' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
            'keywords' => ['nullable', 'array', 'max:50'],
            'keywords.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['type' => 'jenis', 'name' => 'nama', 'parent_id' => 'induk kategori', 'keywords' => 'kata kunci'];
    }

    public function toData(): CategoryData
    {
        return CategoryData::from([...$this->validated(), 'keywords' => $this->validated('keywords') ?? []]);
    }
}
