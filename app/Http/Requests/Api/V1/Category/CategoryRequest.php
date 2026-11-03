<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Http\Requests\Concerns\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or update a category (docs/api-contracts/categories.md).
 */
class CategoryRequest extends FormRequest
{
    use CategoryRules;
}
