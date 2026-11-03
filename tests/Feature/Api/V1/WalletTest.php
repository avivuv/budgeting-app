<?php

use App\Enums\FamilyRole;
use App\Models\Activity;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/wallets.md · Contract: docs/api-contracts/wallets.md
|
| Starts from the golden family (docs/12 §3): Ayah is owner, Ibu is member,
| wallets Tunai 1.200.000, BCA 15.100.000, GoPay 112.500 → total 16.412.500.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

function actAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

/**
 * @return array<string, int>
 */
function walletBalances(bool $includeArchived = false): array
{
    return collect(test()->getJson('/api/v1/wallets'.($includeArchived ? '?include_archived=1' : ''))->assertOk()->json('data'))
        ->pluck('balance', 'name')
        ->all();
}

function totalBalance(): int
{
    return test()->getJson('/api/v1/wallets')->assertOk()->json('meta.total_balance');
}

describe('positive', function () {
    it('[WAL-01] creates a bank wallet typed as "1.500.000" and shows it in the list [BR-A03] [BR-C01]', function () {
        // GIVEN Ayah (owner) and the golden wallets
        actAs($this->golden->ayah);

        // WHEN he saves "Tabungan Agus" with an opening balance typed as text
        $response = $this->postJson('/api/v1/wallets', [
            'name' => 'Tabungan Agus',
            'type' => 'bank',
            'initial_balance' => '1.500.000',
        ]);

        // THEN the answer, the database and the list all show exactly what he typed
        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tabungan Agus')
            ->assertJsonPath('data.type', 'bank')
            ->assertJsonPath('data.type_label', 'Bank')
            ->assertJsonPath('data.initial_balance', 1_500_000)
            ->assertJsonPath('data.balance', 1_500_000)
            ->assertJsonPath('data.balance_formatted', 'Rp 1.500.000')
            ->assertJsonPath('data.is_archived', false);

        $this->assertDatabaseHas('wallets', [
            'id' => $response->json('data.id'),
            'family_id' => $this->golden->family->id,
            'name' => 'Tabungan Agus',
            'initial_balance' => 1_500_000,
        ]);

        expect(walletBalances())->toMatchArray(['Tabungan Agus' => 1_500_000])
            ->and(totalBalance())->toBe(16_412_500 + 1_500_000);
        $this->getJson('/api/v1/wallets')->assertJsonPath('meta.total_balance_formatted', 'Rp 17.912.500');
    });

    it('[WAL-02] creates a wallet without opening balance, colour or icon', function () {
        actAs($this->golden->ayah);

        $this->postJson('/api/v1/wallets', ['name' => 'Celengan', 'type' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('data.initial_balance', 0)
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.color', null)
            ->assertJsonPath('data.icon', null);

        expect(totalBalance())->toBe(16_412_500);
    });

    it('[WAL-03] records a credit card bill typed as "-2jt" as a negative balance [BR-C02] [BR-C06]', function () {
        actAs($this->golden->ayah);

        $this->postJson('/api/v1/wallets', ['name' => 'Kartu Kredit BCA', 'type' => 'credit_card', 'initial_balance' => '-2jt'])
            ->assertCreated()
            ->assertJsonPath('data.initial_balance', -2_000_000)
            ->assertJsonPath('data.balance', -2_000_000)
            ->assertJsonPath('data.balance_formatted', '-Rp 2.000.000');

        expect(totalBalance())->toBe(16_412_500 - 2_000_000);
    });

    it('[WAL-04] shows each balance as opening balance plus income minus expense and transfers [BR-C01] [INV-02]', function () {
        // Ibu (member) only reads.
        actAs($this->golden->ibu, ['read']);

        expect(walletBalances())->toEqual(GoldenFamily::EXPECTED['balances'])
            ->and(totalBalance())->toBe(GoldenFamily::EXPECTED['total_balance']);
    });

    it('[WAL-05] renames a wallet without touching its balance, and logs the change [BR-B14]', function () {
        actAs($this->golden->ayah);
        $bca = $this->golden->wallets['BCA'];

        $this->putJson("/api/v1/wallets/{$bca->id}", ['name' => 'BCA Utama', 'type' => 'bank', 'initial_balance' => 10_000_000])
            ->assertOk()
            ->assertJsonPath('data.name', 'BCA Utama')
            ->assertJsonPath('data.balance', 15_100_000);

        expect($bca->fresh()->name)->toBe('BCA Utama')
            ->and(walletBalances())->toMatchArray(['BCA Utama' => 15_100_000])
            ->and(walletBalances())->not->toHaveKey('BCA');

        $log = Activity::query()->where('subject_type', $bca->getMorphClass())->where('subject_id', $bca->id)->where('event', 'updated')->sole();
        expect($log->attribute_changes['old']['name'])->toBe('BCA')
            ->and($log->attribute_changes['attributes']['name'])->toBe('BCA Utama');
    });

    it('[WAL-06] archives a wallet out of the total and new transactions, then restores it [BR-C02] [BR-B05]', function () {
        actAs($this->golden->ayah);
        $gopay = $this->golden->wallets['GoPay'];
        $newExpense = fn () => $this->postJson('/api/v1/transactions', [
            'type' => 'expense', 'amount' => 10_000, 'date' => '2026-10-31',
            'wallet_id' => $gopay->id, 'category_id' => $this->golden->categories['Makanan']->id,
        ]);

        $this->patchJson("/api/v1/wallets/{$gopay->id}/archive", ['archived' => true])
            ->assertOk()
            ->assertJsonPath('data.is_archived', true);

        expect(totalBalance())->toBe(16_412_500 - 112_500)
            ->and(walletBalances())->not->toHaveKey('GoPay')
            ->and(walletBalances(includeArchived: true))->toMatchArray(['GoPay' => 112_500]);
        $newExpense()->assertUnprocessable()->assertJsonPath('rule', 'BR-B05');

        $this->patchJson("/api/v1/wallets/{$gopay->id}/archive", ['archived' => false])
            ->assertOk()
            ->assertJsonPath('data.is_archived', false);

        expect(totalBalance())->toBe(16_412_500);
        $newExpense()->assertCreated();
    });

    it('[WAL-07] deletes a wallet that was never used', function () {
        actAs($this->golden->ayah);
        $id = $this->postJson('/api/v1/wallets', ['name' => 'Salah Buat', 'type' => 'cash'])->json('data.id');

        $this->deleteJson("/api/v1/wallets/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('wallets', ['id' => $id]);
        expect(walletBalances(includeArchived: true))->not->toHaveKey('Salah Buat');
    });

    it('[WAL-08] lets an admin create and change wallets [BR-B09]', function () {
        actAs(addMember($this->golden->family, FamilyRole::Admin));

        $id = $this->postJson('/api/v1/wallets', ['name' => 'OVO', 'type' => 'ewallet', 'initial_balance' => '250rb'])
            ->assertCreated()
            ->assertJsonPath('data.balance', 250_000)
            ->json('data.id');

        $this->putJson("/api/v1/wallets/{$id}", ['name' => 'OVO Ibu', 'type' => 'ewallet', 'initial_balance' => '250rb'])
            ->assertOk()
            ->assertJsonPath('data.name', 'OVO Ibu');
    });
});

describe('negative', function () {
    it('[WAL-11] rejects an empty or too long name and creates nothing', function () {
        actAs($this->golden->ayah);
        $before = Wallet::count();

        $this->postJson('/api/v1/wallets', ['name' => '', 'type' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Nama wajib diisi.');
        $this->postJson('/api/v1/wallets', ['name' => str_repeat('a', 51), 'type' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'Nama maksimal 50 karakter.');

        expect(Wallet::count())->toBe($before);
    });

    it('[WAL-12] rejects a missing or unknown type and creates nothing', function () {
        actAs($this->golden->ayah);
        $before = Wallet::count();

        $this->postJson('/api/v1/wallets', ['name' => 'Emas'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Jenis wajib diisi.');
        $this->postJson('/api/v1/wallets', ['name' => 'Emas', 'type' => 'gold'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.type.0', 'Jenis yang dipilih tidak valid.');

        expect(Wallet::count())->toBe($before);
    });

    it('[WAL-13] rejects an unreadable opening balance and creates nothing [BR-A03]', function (string $typed) {
        actAs($this->golden->ayah);
        $before = Wallet::count();

        $this->postJson('/api/v1/wallets', ['name' => 'Dompet', 'type' => 'cash', 'initial_balance' => $typed])
            ->assertUnprocessable()
            ->assertJsonPath('errors.initial_balance.0', 'Saldo awal tidak valid.');

        expect(Wallet::count())->toBe($before);
    })->with(['abc', '50,5', '9999999jt']);

    it('[WAL-14] refuses to delete a wallet with transactions and keeps both [BR-C05]', function () {
        actAs($this->golden->ayah);
        $bca = $this->golden->wallets['BCA'];

        $this->deleteJson("/api/v1/wallets/{$bca->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Dompet sudah memiliki transaksi sehingga tidak bisa dihapus. Arsipkan saja.')
            ->assertJsonPath('rule', 'BR-C05');

        expect($bca->fresh())->not->toBeNull()
            ->and(walletBalances())->toMatchArray(['BCA' => 15_100_000]);
    });

    it('[WAL-15] does not let a member create, change or delete wallets [BR-B09]', function () {
        actAs($this->golden->ibu);
        $gopay = $this->golden->wallets['GoPay'];

        $this->postJson('/api/v1/wallets', ['name' => 'Dompet Ibu', 'type' => 'cash'])->assertForbidden();
        $this->putJson("/api/v1/wallets/{$gopay->id}", ['name' => 'GoPay Ibu', 'type' => 'ewallet'])->assertForbidden();
        $this->patchJson("/api/v1/wallets/{$gopay->id}/archive", ['archived' => true])->assertForbidden();
        $this->deleteJson("/api/v1/wallets/{$gopay->id}")->assertForbidden();

        $gopay->refresh();
        expect(Wallet::where('name', 'Dompet Ibu')->exists())->toBeFalse()
            ->and($gopay->name)->toBe('GoPay')
            ->and($gopay->is_archived)->toBeFalse();
    });

    it('[WAL-16] does not let a viewer create a wallet [BR-B09]', function () {
        actAs(addMember($this->golden->family, FamilyRole::Viewer));

        $this->postJson('/api/v1/wallets', ['name' => 'Dompet Viewer', 'type' => 'cash'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Kamu tidak punya akses untuk aksi ini.');

        expect(Wallet::where('name', 'Dompet Viewer')->exists())->toBeFalse();
    });

    it('[WAL-17] does not let a read-only token create a wallet', function () {
        actAs($this->golden->ayah, ['read']);

        $this->postJson('/api/v1/wallets', ['name' => 'Dompet Token', 'type' => 'cash'])->assertForbidden();

        expect(Wallet::where('name', 'Dompet Token')->exists())->toBeFalse();
    });

    it('[WAL-18] hides wallets of another family behind 404 [BR-I06]', function () {
        $neighbour = createFamily(name: 'Tetangga');
        $theirs = Wallet::where('family_id', $neighbour->id)->firstOrFail();
        actAs($this->golden->ayah);

        $this->putJson("/api/v1/wallets/{$theirs->id}", ['name' => 'Diambil', 'type' => 'cash'])->assertNotFound();
        $this->patchJson("/api/v1/wallets/{$theirs->id}/archive", ['archived' => true])->assertNotFound();
        $this->deleteJson("/api/v1/wallets/{$theirs->id}")->assertNotFound();

        $theirs->refresh();
        expect($theirs->name)->not->toBe('Diambil')
            ->and($theirs->is_archived)->toBeFalse();
    });

    it('[WAL-19] lists only the wallets of the own family [BR-I06]', function () {
        createFamily(name: 'Tetangga');
        actAs($this->golden->ayah, ['read']);

        expect(array_keys(walletBalances(includeArchived: true)))->toEqualCanonicalizing(['Tunai', 'BCA', 'GoPay']);
    });

    it('[WAL-20] answers 401 without a login', function () {
        $this->getJson('/api/v1/wallets')->assertUnauthorized();
        $this->postJson('/api/v1/wallets', ['name' => 'X', 'type' => 'cash'])->assertUnauthorized();

        expect(Wallet::where('name', 'X')->exists())->toBeFalse();
    });
});
