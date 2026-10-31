<?php

use App\Enums\FamilyRole;
use App\Models\Category;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->family = createFamily($this->owner);
});

it('shows expense and income trees', function () {
    $this->actingAs($this->owner)
        ->get(route('categories.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('categories/Index')
            ->has('expense', count(config('budgeting.default_categories.expense')))
            ->has('income', count(config('budgeting.default_categories.income')))
            ->where('canManage', true));
});

it('creates a sub-category with comma separated keywords [BR-E01]', function () {
    useFamily($this->family);
    $food = Category::query()->where('name', 'Makanan & Minuman')->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('categories.store'), ['type' => 'expense', 'name' => 'Restoran', 'parent_id' => $food->id, 'keywords' => 'resto, cafe'])
        ->assertRedirect(route('categories.index'));

    useFamily($this->family);
    $child = Category::query()->where('name', 'Restoran')->sole();

    expect($child->parent_id)->toBe($food->id)->and($child->keywords)->toBe(['resto', 'cafe']);
});

it('reports duplicate names on the name field [BR-E02]', function () {
    $this->actingAs($this->owner)
        ->from(route('categories.index'))
        ->post(route('categories.store'), ['type' => 'expense', 'name' => 'Transportasi'])
        ->assertSessionHasErrors('name');
});

it('forbids members from managing categories', function () {
    $member = addMember($this->family, FamilyRole::Member);

    $this->actingAs($member)
        ->post(route('categories.store'), ['type' => 'expense', 'name' => 'Baru'])
        ->assertForbidden();
});

it('merges categories over HTTP [BR-E03]', function () {
    useFamily($this->family);
    $from = Category::query()->where('name', 'Hiburan')->firstOrFail();
    $to = Category::query()->where('name', 'Lain-lain')->where('type', 'expense')->firstOrFail();

    $this->actingAs($this->owner)
        ->post(route('categories.merge', $from->id), ['target_id' => $to->id])
        ->assertRedirect(route('categories.index'));

    useFamily($this->family);
    expect(Category::query()->find($from->id))->toBeNull();
});
