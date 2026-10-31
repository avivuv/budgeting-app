<?php

use App\DTOs\Category\CategoryData;
use App\Enums\CategoryType;
use App\Exceptions\Category\CategoryRuleException;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\Category\CategoryService;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->service = app(CategoryService::class);
});

it('creates a sub-category with normalized keywords [BR-E01] [BR-E05]', function () {
    createFamily();
    $food = Category::query()->where('name', 'Makanan & Minuman')->firstOrFail();

    $child = $this->service->create(new CategoryData(CategoryType::Expense, ' Restoran ', $food->id, keywords: ['Resto', ' resto ', '', 'Cafe']));

    expect($child->name)->toBe('Restoran')
        ->and($child->parent_id)->toBe($food->id)
        ->and($child->keywords)->toBe(['resto', 'cafe']);
});

it('allows only two levels [BR-E01]', function () {
    createFamily();
    $food = Category::query()->where('name', 'Makanan & Minuman')->firstOrFail();
    $child = $this->service->create(new CategoryData(CategoryType::Expense, 'Restoran', $food->id));

    $this->service->create(new CategoryData(CategoryType::Expense, 'Sushi', $child->id));
})->throws(CategoryRuleException::class);

it('requires the parent type to match [BR-E01]', function () {
    createFamily();
    $salary = Category::query()->where('name', 'Gaji')->firstOrFail();

    $this->service->create(new CategoryData(CategoryType::Expense, 'Lembur', $salary->id));
})->throws(CategoryRuleException::class);

it('keeps names unique per type and parent [BR-E02]', function () {
    createFamily();

    expect(fn () => $this->service->create(new CategoryData(CategoryType::Expense, 'Transportasi')))
        ->toThrow(CategoryRuleException::class);

    // Same name is fine for the other type.
    expect($this->service->create(new CategoryData(CategoryType::Income, 'Transportasi'))->type)
        ->toBe(CategoryType::Income);
});

it('refuses to delete a category used by transactions [BR-E03]', function () {
    $golden = GoldenFamily::seed();

    $this->service->delete($golden->categories['Transportasi']);
})->throws(CategoryRuleException::class);

it('merges a category into another and moves all transactions [BR-E03]', function () {
    $golden = GoldenFamily::seed();
    $from = $golden->categories['Restoran'];
    $to = $golden->categories['Makanan'];

    $moved = $this->service->merge($from, $to);

    expect($moved)->toBe(1)
        ->and(Category::query()->find($from->id))->toBeNull()
        ->and(Transaction::query()->where('category_id', $to->id)->sum('amount'))->toEqual(GoldenFamily::EXPECTED['food_including_restaurant']);
});

it('only merges categories of the same type [BR-E03]', function () {
    $golden = GoldenFamily::seed();

    $this->service->merge($golden->categories['Gaji'], $golden->categories['Makanan']);
})->throws(CategoryRuleException::class);
