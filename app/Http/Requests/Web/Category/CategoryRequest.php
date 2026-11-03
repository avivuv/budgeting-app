<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Category;

use App\Http\Requests\Concerns\CategoryRules;
use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    use CategoryRules;
}
