<?php

use App\Enums\FamilyRole;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->owner = User::factory()->create(['name' => 'Ayah']);
    $this->family = createFamily($this->owner);
    $this->ibu = addMember($this->family, FamilyRole::Admin, User::factory()->create(['name' => 'Ibu']));
    $this->tunai = Wallet::query()->where('name', 'Tunai')->firstOrFail();
    $this->bca = Wallet::factory()->for($this->family)->create(['name' => 'BCA']);
    $this->category = Category::query()->where('name', 'Transportasi')->firstOrFail();
});

it('shows who changed what, with readable values [BR-B14]', function () {
    $this->actingAs($this->owner)->post(route('transactions.store'), [
        'type' => 'expense', 'amount' => '50rb', 'date' => '2026-10-31',
        'wallet_id' => $this->tunai->id, 'category_id' => $this->category->id, 'note' => 'bensin',
    ]);
    useFamily($this->family);
    $transaction = Transaction::query()->sole();

    $this->actingAs($this->ibu)->put(route('transactions.update', $transaction->id), [
        'type' => 'expense', 'amount' => '45rb', 'date' => '2026-10-30',
        'wallet_id' => $this->bca->id, 'category_id' => $this->category->id, 'note' => 'bensin',
    ])->assertRedirect(route('transactions.index'));

    $this->actingAs($this->owner)
        ->get(route('transactions.edit', $transaction->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Form')
            ->has('history', 2)
            ->where('history.0.who', 'Ibu')
            ->where('history.0.title', 'mengubah transaksi')
            ->where('history.0.changes', [
                ['field' => 'Nominal', 'old' => 'Rp 50.000', 'new' => 'Rp 45.000'],
                ['field' => 'Tanggal', 'old' => '31 Okt 2026', 'new' => '30 Okt 2026'],
                ['field' => 'Dompet', 'old' => 'Tunai', 'new' => 'BCA'],
            ])
            ->where('history.1.who', 'Ayah')
            ->where('history.1.title', 'mencatat transaksi'));
});

it('only shows history of the active family', function () {
    $this->actingAs($this->owner)->post(route('transactions.store'), [
        'type' => 'expense', 'amount' => '50rb', 'date' => '2026-10-31',
        'wallet_id' => $this->tunai->id, 'category_id' => $this->category->id,
    ]);
    useFamily($this->family);
    $transaction = Transaction::query()->sole();

    // A forged activity row with the same subject but another family id must not appear.
    Activity::query()->create([
        'family_id' => $this->family->id + 999,
        'log_name' => 'transactions',
        'description' => 'updated',
        'event' => 'updated',
        'subject_type' => $transaction->getMorphClass(),
        'subject_id' => $transaction->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('transactions.edit', $transaction->id))
        ->assertInertia(fn (Assert $page) => $page->has('history', 1));
});
