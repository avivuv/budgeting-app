<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Category;

use App\DTOs\Category\CategoryItemData;
use App\Enums\CategoryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Category\CategoryRequest;
use App\Models\Category;
use App\Services\Category\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $tree = fn (CategoryType $type) => $this->categories->tree($type, includeArchived: true)
            ->map(fn (Category $category) => CategoryItemData::fromCategory($category))
            ->values();

        return Inertia::render('categories/Index', [
            'expense' => $tree(CategoryType::Expense),
            'income' => $tree(CategoryType::Income),
            'canManage' => Gate::allows('create', Category::class),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $category = $this->categories->create($request->toData());

        return $this->done("Kategori {$category->name} ditambahkan.");
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $this->categories->update($category, $request->toData());

        return $this->done('Kategori diperbarui.');
    }

    public function archive(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $archived = $request->boolean('archived', true);
        $this->categories->setArchived($category, $archived);

        return $this->done($archived ? 'Kategori diarsipkan.' : 'Kategori diaktifkan kembali.');
    }

    public function merge(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $validated = $request->validate(['target_id' => ['required', 'integer']], [], ['target_id' => 'kategori tujuan']);
        $moved = $this->categories->merge($category, $this->categories->find((int) $validated['target_id']));

        return $this->done("Kategori digabung. {$moved} transaksi dipindahkan.");
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $this->categories->delete($category);

        return $this->done('Kategori dihapus.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('categories.index');
    }
}
