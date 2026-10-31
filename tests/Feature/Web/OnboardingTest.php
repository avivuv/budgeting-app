<?php

use App\Models\Family;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the onboarding page to users without a family', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso']);

    $this->actingAs($user)
        ->get(route('onboarding.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('onboarding/Create')
            ->where('suggestedName', 'Keluarga Santoso'));
});

it('creates the family and opens the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('onboarding.store'), ['name' => 'Keluarga Budi', 'period_start_day' => 25])
        ->assertRedirect(route('dashboard'));

    $family = Family::query()->sole();

    expect($family->name)->toBe('Keluarga Budi')
        ->and($family->period_start_day)->toBe(25)
        ->and($user->fresh()->current_family_id)->toBe($family->id);
});

it('validates the family form', function (array $input, string $field) {
    $this->actingAs(User::factory()->create())
        ->post(route('onboarding.store'), $input)
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'start day too high' => [['name' => 'A', 'period_start_day' => 29], 'period_start_day'],
    'start day zero' => [['name' => 'A', 'period_start_day' => 0], 'period_start_day'],
]);

it('sends members with a family straight to the dashboard', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)->get(route('onboarding.create'))->assertRedirect(route('dashboard'));
});

it('lets members open onboarding to create another family', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)->get(route('onboarding.create', ['new' => 1]))->assertOk();
});

it('switches only to families the user belongs to [BR-I06]', function () {
    $user = User::factory()->create();
    $first = createFamily($user, 'Pertama');
    $second = createFamily($user, 'Kedua');
    $foreign = createFamily(name: 'Tetangga');

    $this->actingAs($user)->put(route('families.switch', $first->id))->assertRedirect(route('dashboard'));
    expect($user->fresh()->current_family_id)->toBe($first->id);

    $this->actingAs($user)->put(route('families.switch', $foreign->id))->assertNotFound();
    expect($user->fresh()->current_family_id)->toBe($first->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('family.name', 'Pertama')
            ->where('family.role', 'owner')
            ->where('family.can.manage', true)
            ->count('families', 2));

    expect($second->id)->not->toBe($first->id);
});
