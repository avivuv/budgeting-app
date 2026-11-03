<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Category;

use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\ArchiveCategoryRequest;
use App\Http\Requests\Api\V1\Category\CategoryRequest;
use App\Http\Requests\Api\V1\Category\MergeCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Categories of the active family (docs/api-contracts/categories.md).
 */
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

        $validated = $request->validate(['type' => ['nullable', Rule::enum(CategoryType::class)]], [], ['type' => 'jenis']);
        $type = isset($validated['type']) ? CategoryType::from($validated['type']) : null;

        return CategoryResource::collection($this->categories->tree($type, $request->boolean('include_archived')));
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        return CategoryResource::make($this->categories->create($request->toData()))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        return CategoryResource::make($this->categories->update($category, $request->toData()));
    }

    public function archive(ArchiveCategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        return CategoryResource::make($this->categories->setArchived($category, $request->boolean('archived')));
    }

    /**
     * Move the transactions and budgets of {category} into target_id, then delete it (BR-E03).
     */
    public function merge(MergeCategoryRequest $request, Category $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        $target = $this->categories->find($request->integer('target_id'));
        $moved = $this->categories->merge($category, $target);

        return CategoryResource::make($target->refresh())
            ->additional(['meta' => ['moved_transactions' => $moved]])
            ->response();
    }

    public function destroy(Category $category): Response
    {
        Gate::authorize('delete', $category);

        $this->categories->delete($category);

        return response()->noContent();
    }
}
