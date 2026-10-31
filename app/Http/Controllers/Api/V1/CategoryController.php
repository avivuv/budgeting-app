<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    /**
     * Category tree, optionally filtered by ?type=expense|income.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        $validated = $request->validate(['type' => ['nullable', Rule::enum(CategoryType::class)]]);
        $type = isset($validated['type']) ? CategoryType::from($validated['type']) : null;

        return CategoryResource::collection($this->categories->tree($type, $request->boolean('include_archived')));
    }
}
