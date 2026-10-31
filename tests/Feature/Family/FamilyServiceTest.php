<?php

use App\Enums\CategoryType;
use App\Enums\FamilyRole;
use App\Models\Category;
use App\Models\Family;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Family\FamilyService;

it('makes the creator the owner and active family', function () {
    $owner = User::factory()->create();
    $family = createFamily($owner, 'Keluarga Budi');

    expect($family->name)->toBe('Keluarga Budi')
        ->and($owner->fresh()->current_family_id)->toBe($family->id)
        ->and(app(FamilyService::class)->roleOf($family, $owner))->toBe(FamilyRole::Owner)
        ->and($family->slug)->toStartWith('keluarga-budi-');
});

it('creates the default wallet and categories for a new family', function () {
    createFamily();

    $expense = count(config('budgeting.default_categories.expense'));
    $income = count(config('budgeting.default_categories.income'));

    expect(Wallet::query()->pluck('name')->all())->toBe(['Tunai'])
        ->and(Category::query()->where('type', CategoryType::Expense)->count())->toBe($expense)
        ->and(Category::query()->where('type', CategoryType::Income)->count())->toBe($income)
        ->and(Category::query()->where('is_default', false)->count())->toBe(0);
});

it('gives each family unique slugs even with the same name', function () {
    $a = createFamily(name: 'Keluarga Sama');
    $b = createFamily(name: 'Keluarga Sama');

    expect($a->slug)->not->toBe($b->slug);
});

it('falls back to another family when the active one is no longer theirs', function () {
    $user = User::factory()->create();
    $first = createFamily($user, 'Pertama');
    $other = Family::factory()->create();

    $user->forceFill(['current_family_id' => $other->id])->save();

    expect(app(FamilyService::class)->resolveActive($user)?->id)->toBe($first->id)
        ->and($user->fresh()->current_family_id)->toBe($first->id);
});

it('returns null when the user has no family', function () {
    $user = User::factory()->create();

    expect(app(FamilyService::class)->resolveActive($user))->toBeNull();
});

it('only switches to families the user belongs to [BR-I06]', function () {
    $user = User::factory()->create();
    $mine = createFamily($user, 'Milikku');
    $second = createFamily($user, 'Kedua');
    $foreign = createFamily(name: 'Orang Lain');

    $service = app(FamilyService::class);

    expect($service->switchTo($user, $mine->id)?->id)->toBe($mine->id)
        ->and($service->switchTo($user, $foreign->id))->toBeNull()
        ->and($user->fresh()->current_family_id)->toBe($mine->id)
        ->and($service->familiesOf($user)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$mine->id, $second->id])->sort()->values()->all());
});
