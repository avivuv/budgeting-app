<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\PersonalAccessToken;

it('creates a read-only token by default and shows it once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('api-tokens.store'), ['name' => 'Shortcut'])
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('new_token');

    $token = PersonalAccessToken::query()->sole();

    expect($token->abilities)->toBe(['read'])
        ->and($token->expires_at?->isFuture())->toBeTrue();
});

it('creates a read and write token', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('api-tokens.store'), ['name' => 'Skrip', 'can_write' => '1']);

    expect(PersonalAccessToken::query()->sole()->abilities)->toBe(['read', 'write']);
});

it('lists tokens without exposing the secret', function () {
    $user = User::factory()->create();
    $user->createToken('Lama', ['read']);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Lama')
            ->missing('tokens.0.token')
            ->where('newToken', null));
});

it('lets a user revoke only their own tokens', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = $user->createToken('Milikku')->accessToken;
    $theirs = $other->createToken('Milik orang')->accessToken;

    $this->actingAs($user)->delete(route('api-tokens.destroy', $theirs->id))->assertNotFound();
    $this->actingAs($user)->delete(route('api-tokens.destroy', $mine->id))->assertRedirect(route('api-tokens.index'));

    expect(PersonalAccessToken::query()->pluck('name')->all())->toBe(['Milik orang']);
});

it('authenticates API calls with the created token', function () {
    $user = User::factory()->create();
    createFamily($user);
    $plain = $user->createToken('Shortcut', ['read'])->plainTextToken;

    $this->withToken($plain)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', $user->email);
});
