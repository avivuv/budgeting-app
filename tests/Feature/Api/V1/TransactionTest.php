<?php

use App\Enums\FamilyRole;
use App\Enums\TransactionSource;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/transactions.md · Contract: docs/api-contracts/transactions.md
|
| Golden family (docs/12 §3), today 31 Oct 2026: Ayah owner, Ibu member;
| Tunai 1.200.000, BCA 15.100.000, GoPay 112.500; income 8.000.000,
| expense 2.287.500. Saldo/total invariants: TransactionRoundTripTest.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

function member(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

/**
 * A valid expense form, as the user would fill it.
 */
function expenseForm(array $overrides = []): array
{
    $golden = test()->golden;

    return [
        'type' => 'expense',
        'amount' => '12.500',
        'date' => '2026-10-30',
        'wallet_id' => $golden->wallets['GoPay']->id,
        'category_id' => $golden->categories['Makanan']->id,
        'note' => 'kopi susu',
        'tags' => ['#Kantor'],
        ...$overrides,
    ];
}

/**
 * @return array<string, int>
 */
function balances(): array
{
    return collect(test()->getJson('/api/v1/wallets')->assertOk()->json('data'))->pluck('balance', 'name')->all();
}

/**
 * @return array{income: int, expense: int}
 */
function totals(string $query = ''): array
{
    return test()->getJson('/api/v1/transactions?'.$query)->assertOk()->json('meta.totals');
}

function goldenTransaction(int $amount): Transaction
{
    return Transaction::query()->where('amount', $amount)->firstOrFail();
}

describe('positive', function () {
    it('[TRX-01] records an expense and reads it back exactly [BR-B07] [BR-C01]', function () {
        member($this->golden->ayah);

        // WHEN Ayah saves the form as typed
        $id = $this->postJson('/api/v1/transactions', expenseForm())->assertCreated()->json('data.id');

        // THEN reading it back gives what he typed, and the numbers follow
        $this->getJson("/api/v1/transactions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'expense')
            ->assertJsonPath('data.amount', 12_500)
            ->assertJsonPath('data.amount_formatted', 'Rp 12.500')
            ->assertJsonPath('data.date', '2026-10-30')
            ->assertJsonPath('data.wallet.name', 'GoPay')
            ->assertJsonPath('data.category.name', 'Makanan & Minuman')
            ->assertJsonPath('data.note', 'kopi susu')
            ->assertJsonPath('data.tags', ['kantor'])
            ->assertJsonPath('data.source', 'api')
            ->assertJsonPath('data.created_by.name', 'Ayah');

        $this->assertDatabaseHas('transactions', ['id' => $id, 'amount' => 12_500, 'created_by' => $this->golden->ayah->id, 'family_id' => $this->golden->family->id]);
        expect(balances()['GoPay'])->toBe(112_500 - 12_500)
            ->and(totals())->toBe(['income' => 8_000_000, 'expense' => 2_287_500 + 12_500]);
    });

    it('[TRX-02] records income typed as "1,5jt" into BCA', function () {
        member($this->golden->ayah);

        $this->postJson('/api/v1/transactions', expenseForm([
            'type' => 'income', 'amount' => '1,5jt', 'wallet_id' => $this->golden->wallets['BCA']->id,
            'category_id' => $this->golden->categories['Gaji']->id, 'note' => 'bonus',
        ]))->assertCreated()->assertJsonPath('data.amount', 1_500_000);

        expect(balances()['BCA'])->toBe(15_100_000 + 1_500_000)
            ->and(totals()['income'])->toBe(8_000_000 + 1_500_000);
    });

    it('[TRX-03] transfers between wallets without touching income or expense [BR-B11]', function () {
        member($this->golden->ayah);

        $this->postJson('/api/v1/transactions', [
            'type' => 'transfer', 'amount' => '1jt', 'date' => '2026-10-31',
            'wallet_id' => $this->golden->wallets['BCA']->id, 'to_wallet_id' => $this->golden->wallets['Tunai']->id,
        ])->assertCreated()
            ->assertJsonPath('data.to_wallet.name', 'Tunai')
            ->assertJsonPath('data.category', null);

        expect(balances())->toMatchArray(['BCA' => 14_100_000, 'Tunai' => 2_200_000])
            ->and(totals())->toBe(['income' => 8_000_000, 'expense' => 2_287_500]);
    });

    it('[TRX-04] reads amounts the way people type them [BR-A03]', function (string $typed, int $stored) {
        member($this->golden->ayah);

        $this->postJson('/api/v1/transactions', expenseForm(['amount' => $typed]))
            ->assertCreated()
            ->assertJsonPath('data.amount', $stored);
    })->with([
        ['75rb', 75_000],
        ['1,5jt', 1_500_000],
        ['2jt300', 2_300_000],
        ['Rp 50.000', 50_000],
    ]);

    it('[TRX-05] saves without note and tags', function () {
        member($this->golden->ayah);

        $this->postJson('/api/v1/transactions', expenseForm(['note' => null, 'tags' => null]))
            ->assertCreated()
            ->assertJsonPath('data.note', null)
            ->assertJsonPath('data.tags', []);
    });

    it('[TRX-06] moves the amount between wallets when wallet and amount change [BR-C04] [BR-B14]', function () {
        member($this->golden->ayah);
        $tunai = $this->golden->wallets['Tunai'];
        $bca = $this->golden->wallets['BCA'];
        $id = $this->postJson('/api/v1/transactions', expenseForm(['amount' => '50rb', 'wallet_id' => $tunai->id]))->json('data.id');

        $this->putJson("/api/v1/transactions/{$id}", expenseForm(['amount' => '75rb', 'wallet_id' => $bca->id]))
            ->assertOk()
            ->assertJsonPath('data.amount', 75_000)
            ->assertJsonPath('data.wallet.name', 'BCA')
            ->assertJsonPath('data.source', 'api')
            ->assertJsonPath('data.created_by.name', 'Ayah');

        expect(balances())->toMatchArray(['Tunai' => 1_200_000, 'BCA' => 15_100_000 - 75_000]);

        $changes = collect($this->getJson("/api/v1/transactions/{$id}/history")->json('data.0.changes'))->pluck('new', 'field')->all();
        expect($changes)->toMatchArray(['Nominal' => 'Rp 75.000', 'Dompet' => 'BCA']);
    });

    it('[TRX-07] deletes softly: gone from list, totals and balances [BR-B08]', function () {
        member($this->golden->ayah);
        $bill = goldenTransaction(1_500_000);

        $this->deleteJson("/api/v1/transactions/{$bill->id}")->assertNoContent();

        $this->getJson("/api/v1/transactions/{$bill->id}")->assertNotFound();
        $this->assertSoftDeleted('transactions', ['id' => $bill->id]);
        expect(totals()['expense'])->toBe(2_287_500 - 1_500_000)
            ->and(balances()['BCA'])->toBe(15_100_000 + 1_500_000);
    });

    it('[TRX-08] replays the first answer for a repeated Idempotency-Key [BR-B13] [INV-11]', function () {
        member($this->golden->ayah);
        $before = Transaction::count();

        $first = $this->postJson('/api/v1/transactions', expenseForm(), ['Idempotency-Key' => 'a3f1c2d4-0001'])->assertCreated();
        $second = $this->postJson('/api/v1/transactions', expenseForm(), ['Idempotency-Key' => 'a3f1c2d4-0001'])->assertCreated();

        $second->assertHeader('Idempotent-Replayed', 'true');
        expect($second->json('data.id'))->toBe($first->json('data.id'))
            ->and(Transaction::count())->toBe($before + 1);
    });

    it('[TRX-09] filters expenses of a category including its sub-categories [BR-E04] [INV-06]', function () {
        member($this->golden->ibu, ['read']);
        $food = $this->golden->categories['Makanan'];

        $response = $this->getJson("/api/v1/transactions?type=expense&category_id[]={$food->id}&from=2026-10-01&to=2026-10-31")->assertOk();

        expect(collect($response->json('data'))->pluck('amount')->sort()->values()->all())->toBe([250_000, 400_000])
            ->and($response->json('meta.totals'))->toBe(['income' => 0, 'expense' => GoldenFamily::EXPECTED['food_including_restaurant']]);
    });

    it('[TRX-10] sorts by amount and pages, with daily subtotals for the page [INV-06]', function () {
        member($this->golden->ibu, ['read']);

        $response = $this->getJson('/api/v1/transactions?sort=-amount&per_page=2&type=expense')->assertOk();

        expect(collect($response->json('data'))->pluck('amount')->all())->toBe([1_500_000, 400_000])
            ->and($response->json('meta.total'))->toBe(5)
            ->and($response->json('meta.last_page'))->toBe(3)
            ->and($response->json('meta.daily'))->toBe([
                '2026-10-05' => ['income' => 0, 'expense' => 1_500_000],
                '2026-10-20' => ['income' => 0, 'expense' => 400_000],
            ]);
    });

    it('[TRX-11] previews quick entry lines without saving [BR-E05] [BR-B12]', function () {
        member($this->golden->ayah);
        $before = Transaction::count();

        $preview = $this->postJson('/api/v1/transactions/parse', ['text' => "bensin 50rb\nparkir 5rb @gopay kemarin"])
            ->assertOk()
            ->json('data');

        expect($preview)->toHaveCount(2)
            ->and($preview[0])->toMatchArray(['amount' => 50_000, 'category_name' => 'Transportasi', 'date' => '2026-10-31', 'complete' => true])
            ->and($preview[1])->toMatchArray(['amount' => 5_000, 'wallet_id' => $this->golden->wallets['GoPay']->id, 'wallet_name' => 'GoPay', 'date' => '2026-10-30'])
            ->and($preview[1])->toHaveKeys(['category_id', 'type_label', 'amount_formatted', 'missing'])
            ->and(Transaction::count())->toBe($before);
    });

    it('[TRX-12] deletes three selected transactions at once [BR-B08] [BR-B14]', function () {
        member($this->golden->ayah);
        $ids = [goldenTransaction(250_000)->id, goldenTransaction(87_500)->id, goldenTransaction(50_000)->id];

        $this->postJson('/api/v1/transactions/bulk', ['action' => 'delete', 'ids' => $ids])
            ->assertOk()
            ->assertJsonPath('message', '3 transaksi dihapus.')
            ->assertJsonPath('meta.affected', 3);

        expect(Transaction::whereIn('id', $ids)->count())->toBe(0)
            ->and(totals()['expense'])->toBe(2_287_500 - 250_000 - 87_500 - 50_000)
            ->and(Activity::query()->whereIn('subject_id', $ids)->where('subject_type', (new Transaction)->getMorphClass())->where('event', 'deleted')->count())->toBe(3);
    });

    it('[TRX-13] moves two expenses to another category at once [BR-B02] [BR-B14]', function () {
        member($this->golden->ayah);
        $belanja = $this->golden->categories['Belanja'];
        $ids = [goldenTransaction(250_000)->id, goldenTransaction(50_000)->id];

        $this->postJson('/api/v1/transactions/bulk', ['action' => 'category', 'ids' => $ids, 'category_id' => $belanja->id])
            ->assertOk()
            ->assertJsonPath('meta.affected', 2);

        expect(Transaction::whereIn('id', $ids)->pluck('category_id')->unique()->all())->toBe([$belanja->id]);
        $changes = $this->getJson("/api/v1/transactions/{$ids[0]}/history")->json('data.0.changes');
        expect($changes)->toBe([['field' => 'Kategori', 'old' => 'Makanan & Minuman', 'new' => 'Belanja Harian']]);
    });

    it('[TRX-14] shows who created and changed a transaction, newest first [BR-B14]', function () {
        member($this->golden->ayah);
        $id = $this->postJson('/api/v1/transactions', expenseForm())->json('data.id');
        $this->travel(1)->hours();
        $this->putJson("/api/v1/transactions/{$id}", expenseForm(['amount' => '15rb']));

        $history = $this->getJson("/api/v1/transactions/{$id}/history")->assertOk()->json('data');

        expect(collect($history)->pluck('event')->all())->toBe(['updated', 'created'])
            ->and($history[1]['who'])->toBe('Ayah')
            ->and($history[1]['at'])->toBe('2026-10-31T12:00:00+07:00')
            ->and($history[0]['at'])->toBe('2026-10-31T13:00:00+07:00')
            ->and($history[0]['changes'])->toBe([['field' => 'Nominal', 'old' => 'Rp 12.500', 'new' => 'Rp 15.000']]);
    });

    it('[TRX-15] lists the family tags alphabetically', function () {
        member($this->golden->ayah);
        $this->postJson('/api/v1/transactions', expenseForm(['tags' => ['mudik', '#Kantor']]))->assertCreated();

        $this->getJson('/api/v1/tags')->assertOk()->assertExactJson(['data' => ['kantor', 'mudik']]);
    });

    it('[TRX-16] records the web as "web" and Android as "api", otherwise identical [BR-B07] [BR-B12] [INV-01]', function () {
        config(['sanctum.stateful' => ['spa.test']]);

        $this->withHeader('Referer', 'http://spa.test/')->actingAs($this->golden->ayah, 'web')
            ->postJson('/api/v1/transactions', expenseForm())->assertCreated()->assertJsonPath('data.source', 'web');

        app('auth')->forgetGuards();
        $token = $this->golden->ayah->createToken('Pixel', ['read', 'write'])->plainTextToken;
        $this->withHeaders(['Referer' => '', 'Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/transactions', expenseForm())->assertCreated()->assertJsonPath('data.source', 'api');

        useFamily($this->golden->family);
        [$web, $api] = Transaction::query()->with('tags')->latest('id')->take(2)->get()->reverse()->values()->all();
        $columns = ['type', 'amount', 'date', 'wallet_id', 'to_wallet_id', 'category_id', 'note', 'created_by', 'family_id'];
        expect($web->only($columns))->toEqual($api->only($columns))
            ->and($web->tags->pluck('name'))->toEqual($api->tags->pluck('name'))
            ->and($web->source)->toBe(TransactionSource::Web)
            ->and($api->source)->toBe(TransactionSource::Api);
    });

    it('[TRX-17] saves a spend that drives GoPay below zero, with a warning [BR-C03]', function () {
        member($this->golden->ayah);

        $this->postJson('/api/v1/transactions', expenseForm(['amount' => '120rb']))
            ->assertCreated()
            ->assertJsonPath('meta.warnings', ['Saldo GoPay menjadi minus (-Rp 7.500).']);

        expect(balances()['GoPay'])->toBe(-7_500);
    });
});

describe('negative', function () {
    it('[TRX-21] rejects an empty, zero, negative or unreadable amount [BR-A02] [BR-A03]', function (mixed $typed, string $message) {
        member($this->golden->ayah);
        $before = Transaction::count();

        $this->postJson('/api/v1/transactions', expenseForm(['amount' => $typed]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', $message);

        expect(Transaction::count())->toBe($before);
    })->with([
        'empty' => [null, 'Nominal wajib diisi.'],
        'zero' => [0, 'Nominal minimal 1.'],
        'negative' => [-5_000, 'Nominal minimal 1.'],
        'text' => ['abc', 'Nominal harus berupa bilangan bulat.'],
        'cents' => ['50,5', 'Nominal harus berupa bilangan bulat.'],
    ]);

    it('[TRX-22] rejects a date in the future, too old or badly written [BR-B06]', function (string $date, string $message) {
        member($this->golden->ayah);
        $before = Transaction::count();

        $this->postJson('/api/v1/transactions', expenseForm(['date' => $date]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.date.0', $message);

        expect(Transaction::count())->toBe($before);
    })->with([
        'tomorrow' => ['2026-11-01', 'Tanggal transaksi tidak boleh melewati hari ini.'],
        'too old' => ['2021-10-30', 'Tanggal transaksi tidak boleh lebih dari 5 tahun yang lalu.'],
        'format' => ['31/10/2026', 'Tanggal harus sesuai format Y-m-d.'],
    ]);

    it('[TRX-23] needs a category of the same type for an expense [BR-B02]', function () {
        member($this->golden->ayah);
        $before = Transaction::count();

        $this->postJson('/api/v1/transactions', expenseForm(['category_id' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_id.0', 'Kategori wajib diisi.');
        $this->postJson('/api/v1/transactions', expenseForm(['category_id' => $this->golden->categories['Gaji']->id]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.category_id.0', 'Jenis kategori tidak sesuai dengan jenis transaksi.')
            ->assertJsonPath('rule', 'BR-B02');

        expect(Transaction::count())->toBe($before);
    });

    it('[TRX-24] needs a different target wallet for a transfer [BR-B03]', function () {
        member($this->golden->ayah);
        $bca = $this->golden->wallets['BCA'];
        $transfer = ['type' => 'transfer', 'amount' => '1jt', 'date' => '2026-10-31', 'wallet_id' => $bca->id];

        $this->postJson('/api/v1/transactions', $transfer)
            ->assertUnprocessable()
            ->assertJsonPath('errors.to_wallet_id.0', 'Dompet tujuan wajib diisi.');
        $this->postJson('/api/v1/transactions', [...$transfer, 'to_wallet_id' => $bca->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.to_wallet_id.0', 'Dompet asal dan tujuan transfer tidak boleh sama.');
    });

    it('[TRX-25] refuses an archived wallet for new transactions but keeps old ones editable [BR-B05]', function () {
        member($this->golden->ayah);
        $gopay = $this->golden->wallets['GoPay'];
        $old = goldenTransaction(87_500);
        $gopay->update(['is_archived' => true]);

        $this->postJson('/api/v1/transactions', expenseForm())
            ->assertUnprocessable()
            ->assertJsonPath('errors.wallet_id.0', 'Dompet ini sudah diarsipkan dan tidak bisa dipakai untuk transaksi baru.');

        $this->putJson("/api/v1/transactions/{$old->id}", [
            'type' => 'expense', 'amount' => '90rb', 'date' => '2026-10-10',
            'wallet_id' => $gopay->id, 'category_id' => $this->golden->categories['Belanja']->id,
        ])->assertOk()->assertJsonPath('data.amount', 90_000);
    });

    it('[TRX-26] cannot use a wallet or category of another family [BR-B04] [BR-I06]', function () {
        $neighbour = createFamily(name: 'Tetangga');
        $theirWallet = Wallet::where('family_id', $neighbour->id)->firstOrFail();
        $theirCategory = Category::where('family_id', $neighbour->id)->where('name', 'Transportasi')->firstOrFail();
        useFamily($this->golden->family);
        member($this->golden->ayah);
        $before = Transaction::count();

        $this->postJson('/api/v1/transactions', expenseForm(['wallet_id' => $theirWallet->id]))->assertNotFound();
        $this->postJson('/api/v1/transactions', expenseForm(['category_id' => $theirCategory->id]))->assertNotFound();

        expect(Transaction::count())->toBe($before);
    });

    it('[TRX-27] does not let a viewer or a read-only token record [BR-B09]', function (string $who) {
        match ($who) {
            'viewer' => member(addMember($this->golden->family, FamilyRole::Viewer)),
            'read token' => member($this->golden->ayah, ['read']),
        };
        $before = Transaction::count();

        $this->postJson('/api/v1/transactions', expenseForm())->assertForbidden();
        $this->postJson('/api/v1/transactions/parse', ['text' => 'bensin 50rb'])->assertForbidden();

        expect(Transaction::count())->toBe($before);
    })->with(['viewer', 'read token']);

    it('[TRX-28] does not let a member change or delete someone else\'s transaction [BR-B09]', function () {
        member($this->golden->ibu);
        $ayahs = goldenTransaction(1_500_000);

        $this->putJson("/api/v1/transactions/{$ayahs->id}", expenseForm(['amount' => '1rb']))->assertForbidden();
        $this->deleteJson("/api/v1/transactions/{$ayahs->id}")->assertForbidden();

        expect($ayahs->fresh()->amount)->toBe(1_500_000)
            ->and($ayahs->fresh()->trashed())->toBeFalse();

        // Her own transaction she may change.
        $this->putJson('/api/v1/transactions/'.goldenTransaction(250_000)->id, expenseForm(['amount' => '260rb', 'wallet_id' => $this->golden->wallets['Tunai']->id, 'date' => '2026-10-03']))->assertOk();
    });

    it('[TRX-29] hides transactions of another family and deleted ones behind 404 [BR-I06] [BR-B08]', function () {
        $neighbour = createFamily(name: 'Tetangga');
        $theirs = Transaction::factory()->expense(10_000, Wallet::where('family_id', $neighbour->id)->firstOrFail(), Category::where('family_id', $neighbour->id)->where('type', 'expense')->firstOrFail())->create();
        useFamily($this->golden->family);
        $deleted = Transaction::onlyTrashed()->firstOrFail();
        member($this->golden->ayah);

        foreach ([$theirs->id, $deleted->id] as $id) {
            $this->getJson("/api/v1/transactions/{$id}")->assertNotFound();
            $this->getJson("/api/v1/transactions/{$id}/history")->assertNotFound();
            $this->putJson("/api/v1/transactions/{$id}", expenseForm())->assertNotFound();
            $this->deleteJson("/api/v1/transactions/{$id}")->assertNotFound();
        }
    });

    it('[TRX-30] changes nothing when one selected transaction is not the member\'s [BR-B09]', function () {
        member($this->golden->ibu);
        $hers = goldenTransaction(250_000);
        $ayahs = goldenTransaction(1_500_000);

        $this->postJson('/api/v1/transactions/bulk', ['action' => 'delete', 'ids' => [$hers->id, $ayahs->id]])->assertForbidden();

        expect(Transaction::whereIn('id', [$hers->id, $ayahs->id])->count())->toBe(2);
    });

    it('[TRX-31] changes nothing when a bulk category change includes a transfer [BR-B03]', function () {
        member($this->golden->ayah);
        $transfer = Transaction::where('type', 'transfer')->firstOrFail();
        $expense = goldenTransaction(50_000);

        $this->postJson('/api/v1/transactions/bulk', ['action' => 'category', 'ids' => [$expense->id, $transfer->id], 'category_id' => $this->golden->categories['Belanja']->id])
            ->assertUnprocessable()
            ->assertJsonPath('rule', 'BR-B03');

        expect($expense->fresh()->category_id)->toBe($this->golden->categories['Transportasi']->id);
    });

    it('[TRX-32] validates the bulk action form', function (array $form, string $field) {
        member($this->golden->ayah);

        $this->postJson('/api/v1/transactions/bulk', $form)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'no ids' => [['action' => 'delete', 'ids' => []], 'ids'],
        'too many' => [['action' => 'delete', 'ids' => range(1, 101)], 'ids'],
        'unknown action' => [['action' => 'archive', 'ids' => [1]], 'action'],
        'no category' => [['action' => 'category', 'ids' => [1]], 'category_id'],
    ]);

    it('[TRX-33] rejects an invalid filter', function (string $query, string $field) {
        member($this->golden->ayah, ['read']);

        $this->getJson('/api/v1/transactions?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    })->with([
        'to before from' => ['from=2026-10-31&to=2026-10-01', 'to'],
        'unknown sort' => ['sort=name', 'sort'],
        'page too big' => ['per_page=500', 'per_page'],
    ]);

    it('[TRX-34] reads another family of the user with X-Family-Id, never a stranger\'s [BR-I06]', function () {
        $second = createFamily($this->golden->ayah, 'Keluarga Kedua');
        $stranger = createFamily(name: 'Tetangga');
        useFamily($this->golden->family);
        member($this->golden->ayah, ['read']);

        $this->withHeader('X-Family-Id', (string) $second->id)->getJson('/api/v1/transactions')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->withHeader('X-Family-Id', (string) $stranger->id)->getJson('/api/v1/transactions')
            ->assertNotFound();
    });
});
