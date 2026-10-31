<?php

use App\Enums\FamilyRole;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

beforeEach(fn () => $this->travelTo('2026-10-31 12:00:00'));

it('returns the same balances as the web [BR-C01] [INV-02]', function () {
    $golden = GoldenFamily::seed();
    Sanctum::actingAs($golden->ayah, ['read']);

    $response = $this->getJson('/api/v1/wallets')->assertOk();

    expect(collect($response->json('data'))->pluck('balance', 'name')->sortKeys()->all())
        ->toBe(collect(GoldenFamily::EXPECTED['balances'])->sortKeys()->all())
        ->and($response->json('meta.total_balance'))->toBe(GoldenFamily::EXPECTED['total_balance']);
});

it('creates, updates and deletes wallets', function () {
    $golden = GoldenFamily::seed();
    Sanctum::actingAs($golden->ayah, ['read', 'write']);

    $id = $this->postJson('/api/v1/wallets', ['name' => 'OVO', 'type' => 'ewallet', 'initial_balance' => '250rb'])
        ->assertCreated()
        ->assertJsonPath('data.balance', 250_000)
        ->json('data.id');

    $this->putJson("/api/v1/wallets/{$id}", ['name' => 'OVO Ibu', 'type' => 'ewallet', 'initial_balance' => 250_000])
        ->assertOk()
        ->assertJsonPath('data.name', 'OVO Ibu');

    $this->deleteJson("/api/v1/wallets/{$id}")->assertNoContent();
});

it('refuses to delete a used wallet with a JSON message [BR-C05]', function () {
    $golden = GoldenFamily::seed();
    Sanctum::actingAs($golden->ayah, ['read', 'write']);

    $this->deleteJson("/api/v1/wallets/{$golden->wallets['BCA']->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Dompet sudah memiliki transaksi sehingga tidak bisa dihapus. Arsipkan saja.');
});

it('lets members read but not manage wallets', function () {
    $golden = GoldenFamily::seed();
    Sanctum::actingAs($golden->ibu, ['read', 'write']);

    $this->getJson('/api/v1/wallets')->assertOk();
    $this->postJson('/api/v1/wallets', ['name' => 'X', 'type' => 'cash'])->assertForbidden();

    expect(Wallet::query()->where('name', 'X')->exists())->toBeFalse();
});

it('lists the category tree filtered by type', function () {
    $golden = GoldenFamily::seed();
    Sanctum::actingAs($golden->ibu, ['read']);

    $this->getJson('/api/v1/categories?type=income')
        ->assertOk()
        ->assertJsonCount(count(config('budgeting.default_categories.income')), 'data')
        ->assertJsonPath('data.0.type', 'income');

    $this->getJson('/api/v1/categories?type=purple')->assertUnprocessable();
});

it('returns JSON 409 for users without a family', function () {
    Sanctum::actingAs(User::factory()->create(), ['read']);

    $this->get('/api/v1/me')->assertStatus(409)->assertJsonPath('message', 'Buat atau gabung ke keluarga terlebih dahulu.');
});

it('rejects owner-only actions for viewers', function () {
    $golden = GoldenFamily::seed();
    $viewer = addMember($golden->family, FamilyRole::Viewer);
    Sanctum::actingAs($viewer, ['read', 'write']);

    $this->deleteJson("/api/v1/wallets/{$golden->wallets['GoPay']->id}")->assertForbidden();
});
