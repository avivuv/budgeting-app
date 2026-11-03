<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Merge a category into another one (docs/api-contracts/categories.md, BR-E03).
 */
class MergeCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['target_id' => ['required', 'integer']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['target_id' => 'kategori tujuan'];
    }
}
