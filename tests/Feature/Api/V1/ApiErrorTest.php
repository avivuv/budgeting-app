<?php

use App\Enums\FamilyRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;

/*
| Common error bodies of the REST API (docs/api-contracts/README.md §Error,
| scenarios ERR-xx in docs/test-cases/auth.md).
*/

it('[ERR-01] answers 401 in Indonesian without a login', function () {
    $this->getJson('/api/v1/wallets')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Silakan login terlebih dahulu.']);
});

it('[ERR-02] answers 403 in Indonesian when the role may not do it [BR-B09]', function () {
    $family = createFamily();
    Sanctum::actingAs(addMember($family, FamilyRole::Viewer), ['read', 'write']);

    $this->postJson('/api/v1/wallets', ['name' => 'Dompet Viewer', 'type' => 'cash'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Kamu tidak punya akses untuk aksi ini.']);

    expect(Wallet::where('name', 'Dompet Viewer')->exists())->toBeFalse();
});

it('[ERR-02] answers 403 in Indonesian for a token without the write ability', function () {
    $owner = User::factory()->create();
    createFamily($owner);
    Sanctum::actingAs($owner, ['read']);

    $this->postJson('/api/v1/wallets', ['name' => 'Dompet', 'type' => 'cash'])
        ->assertForbidden()
        ->assertExactJson(['message' => 'Kamu tidak punya akses untuk aksi ini.']);
});

it('[ERR-03] answers 404 in Indonesian for data of another family [BR-I06]', function () {
    $neighbour = createFamily(name: 'Tetangga');
    $theirWallet = Wallet::where('family_id', $neighbour->id)->firstOrFail();

    $owner = User::factory()->create();
    createFamily($owner);
    Sanctum::actingAs($owner, ['read', 'write']);

    $this->putJson("/api/v1/wallets/{$theirWallet->id}", ['name' => 'Diambil', 'type' => 'cash'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Data tidak ditemukan.']);

    expect($theirWallet->fresh()->name)->not->toBe('Diambil');
});

it('[ERR-04] answers 409 with code no_family for a user without a family', function () {
    Sanctum::actingAs(User::factory()->create(), ['read']);

    $this->getJson('/api/v1/wallets')
        ->assertStatus(409)
        ->assertExactJson(['message' => 'Buat atau gabung ke keluarga terlebih dahulu.', 'code' => 'no_family']);
});

it('[ERR-05] writes validation messages in Indonesian with Indonesian field names', function () {
    $owner = User::factory()->create();
    createFamily($owner);
    Sanctum::actingAs($owner, ['read', 'write']);

    $this->postJson('/api/v1/wallets', ['name' => '', 'type' => 'emas'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Nama wajib diisi.')
        ->assertJsonPath('errors.type.0', 'Jenis yang dipilih tidak valid.');
});

it('[ERR-06] names the broken business rule [BR-C05]', function () {
    $owner = User::factory()->create();
    $family = createFamily($owner);
    $wallet = Wallet::where('family_id', $family->id)->firstOrFail();
    $category = Category::where('family_id', $family->id)->where('type', 'expense')->firstOrFail();
    Transaction::factory()->expense(10_000, $wallet, $category)->create(['created_by' => $owner->id]);
    Sanctum::actingAs($owner, ['read', 'write']);

    $this->deleteJson("/api/v1/wallets/{$wallet->id}")
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Dompet sudah memiliki transaksi sehingga tidak bisa dihapus. Arsipkan saja.',
            'errors' => ['general' => ['Dompet sudah memiliki transaksi sehingga tidak bisa dihapus. Arsipkan saja.']],
            'rule' => 'BR-C05',
        ]);

    expect($wallet->fresh())->not->toBeNull();
});
