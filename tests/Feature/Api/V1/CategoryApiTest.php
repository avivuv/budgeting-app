<?php

use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Category reads. Rewritten into scenarios when the Kategori feature is checked
| (docs/test-cases/categories.md, M2).
*/

it('lists the category tree filtered by type', function () {
    $golden = GoldenFamily::seed();
    Sanctum::actingAs($golden->ibu, ['read']);

    $this->getJson('/api/v1/categories?type=income')
        ->assertOk()
        ->assertJsonCount(count(config('budgeting.default_categories.income')), 'data')
        ->assertJsonPath('data.0.type', 'income');

    $this->getJson('/api/v1/categories?type=purple')->assertUnprocessable();
});
