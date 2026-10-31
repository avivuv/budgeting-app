<?php

use App\Enums\Appearance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('defaults new users to following the system theme', function () {
    $user = User::factory()->create()->fresh();

    expect($user->appearance)->toBe(Appearance::System);
});

it('saves the appearance preference to the user account', function (string $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/dashboard')
        ->patch('/settings/appearance', ['appearance' => $value])
        ->assertRedirect('/dashboard')
        ->assertCookie('appearance', $value, encrypted: false);

    expect($user->fresh()->appearance)->toBe(Appearance::from($value));
})->with(['light', 'dark', 'system']);

it('rejects unknown appearance values', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/appearance', ['appearance' => 'purple'])
        ->assertSessionHasErrors('appearance');

    expect($user->fresh()->appearance)->toBe(Appearance::System);
});

it('requires authentication to save the preference', function () {
    $this->patch('/settings/appearance', ['appearance' => 'dark'])
        ->assertRedirect('/login');
});

it('shares the saved preference with the frontend, ignoring the device cookie', function () {
    $user = User::factory()->create(['appearance' => Appearance::Dark]);

    $this->actingAs($user)
        ->withUnencryptedCookie('appearance', 'light')
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('appearance', 'dark'));
});

it('uses the cookie preference for guests', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get('/login')
        ->assertInertia(fn (Assert $page) => $page->where('appearance', 'dark'));
});

it('renders the dark class server-side for dark preference', function () {
    $user = User::factory()->create(['appearance' => Appearance::Dark]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertSee('class="dark"', escape: false);
});
