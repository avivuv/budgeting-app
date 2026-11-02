<?php

use App\Models\User;

it('sends guests from the root url to the login page', function () {
    $this->get(route('home'))->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('sends members from the root url to the dashboard', function () {
    $user = User::factory()->create();
    createFamily($user);

    $this->actingAs($user)->get(route('home'))->assertRedirect('/dashboard');
});
