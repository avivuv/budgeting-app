<?php

use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends users without a family to onboarding', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.create'));
});

it('shows the dashboard to family members', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
