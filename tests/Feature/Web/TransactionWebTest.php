<?php

use App\Enums\FamilyRole;
use App\Enums\TransactionSource;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->owner = User::factory()->create(['name' => 'Ayah']);
    $this->family = createFamily($this->owner);
    $this->wallet = Wallet::query()->where('name', 'Tunai')->firstOrFail();
    $this->category = Category::query()->where('name', 'Transportasi')->firstOrFail();
});

function txInput(array $overrides = []): array
{
    return [
        'type' => 'expense',
        'amount' => '50rb',
        'date' => '2026-10-31',
        'wallet_id' => test()->wallet->id,
        'category_id' => test()->category->id,
        'note' => 'bensin',
        'tags' => 'mudik, Kantor',
        ...$overrides,
    ];
}

it('stores a transaction typed as "50rb" [BR-A03] [BR-B07]', function () {
    $this->actingAs($this->owner)
        ->post(route('transactions.store'), txInput())
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::query()->sole();

    expect($transaction->amount)->toBe(50_000)
        ->and($transaction->source)->toBe(TransactionSource::Web)
        ->and($transaction->created_by)->toBe($this->owner->id)
        ->and($transaction->tags()->pluck('name')->sort()->values()->all())->toBe(['kantor', 'mudik']);
});

it('stores a transaction when the optional tag and note fields are left empty', function () {
    // Empty inputs arrive as null (ConvertEmptyStringsToNull).
    $this->actingAs($this->owner)
        ->post(route('transactions.store'), txInput(['tags' => '', 'note' => '']))
        ->assertRedirect(route('transactions.index'));

    $transaction = Transaction::query()->sole();

    expect($transaction->tags()->count())->toBe(0)
        ->and($transaction->note)->toBeNull();
});

it('returns to the form for "save and add another"', function () {
    $this->actingAs($this->owner)
        ->post(route('transactions.store'), txInput(['add_another' => 1]))
        ->assertRedirect(route('transactions.create'));
});

it('lists transactions with formatted values from the server', function () {
    $this->actingAs($this->owner)->post(route('transactions.store'), txInput());

    $this->actingAs($this->owner)
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->where('transactions.total', 1)
            ->where('transactions.data.0.amountFormatted', 'Rp 50.000')
            ->where('transactions.data.0.categoryName', 'Transportasi')
            ->where('transactions.data.0.creatorName', 'Ayah')
            ->where('transactions.data.0.dateFormatted', '31 Okt 2026')
            ->where('transactions.data.0.canEdit', true));
});

it('rejects invalid input with field errors', function (array $overrides, string $field) {
    $this->actingAs($this->owner)
        ->from(route('transactions.create'))
        ->post(route('transactions.store'), txInput($overrides))
        ->assertSessionHasErrors($field);

    expect(Transaction::query()->count())->toBe(0);
})->with([
    'unparseable amount' => [['amount' => '50,5'], 'amount'],
    'zero amount' => [['amount' => '0'], 'amount'],
    'bad date' => [['date' => '31-10-2026'], 'date'],
    'future date [BR-B06]' => [['date' => '2026-11-01'], 'date'],
    'missing category [BR-B02]' => [['category_id' => null], 'category_id'],
    'income category on expense [BR-B02]' => [fn () => ['category_id' => Category::query()->where('name', 'Gaji')->value('id')], 'category_id'],
    'transfer to same wallet [BR-B03]' => [fn () => ['type' => 'transfer', 'to_wallet_id' => test()->wallet->id], 'to_wallet_id'],
]);

it('applies the role matrix for editing [BR-B09]', function (FamilyRole $role, bool $own, bool $allowed) {
    $actor = addMember($this->family, $role);
    $author = $own ? $actor : $this->owner;

    useFamily($this->family);
    $transaction = Transaction::factory()->expense(10_000, $this->wallet, $this->category)->create(['created_by' => $author->id]);

    $response = $this->actingAs($actor)->put(route('transactions.update', $transaction->id), txInput(['amount' => '20rb']));

    $allowed ? $response->assertRedirect(route('transactions.index')) : $response->assertForbidden();
    useFamily($this->family);
    expect($transaction->fresh()->amount)->toBe($allowed ? 20_000 : 10_000);
})->with([
    'member own' => [FamilyRole::Member, true, true],
    'member other' => [FamilyRole::Member, false, false],
    'admin other' => [FamilyRole::Admin, false, true],
    'viewer own' => [FamilyRole::Viewer, true, false],
]);

it('forbids viewers from creating transactions [BR-B09]', function () {
    $viewer = addMember($this->family, FamilyRole::Viewer);

    $this->actingAs($viewer)->get(route('transactions.create'))->assertForbidden();
    $this->actingAs($viewer)->post(route('transactions.store'), txInput())->assertForbidden();
    $this->actingAs($viewer)->get(route('transactions.index'))->assertOk();
});

it('returns 404 for transactions of another family [BR-I06]', function () {
    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');
    $theirWallet = Wallet::query()->firstOrFail();
    $theirCategory = Category::query()->where('name', 'Transportasi')->firstOrFail();
    $theirs = Transaction::factory()->expense(99_000, $theirWallet, $theirCategory)->create(['created_by' => $neighbour->id]);

    $this->actingAs($this->owner)->get(route('transactions.edit', $theirs->id))->assertNotFound();
    $this->actingAs($this->owner)->delete(route('transactions.destroy', $theirs->id))->assertNotFound();
});

it('soft deletes from the list [BR-B08]', function () {
    $this->actingAs($this->owner)->post(route('transactions.store'), txInput());
    useFamily($this->family);
    $transaction = Transaction::query()->sole();

    $this->actingAs($this->owner)->delete(route('transactions.destroy', $transaction->id))->assertRedirect(route('transactions.index'));

    useFamily($this->family);
    expect(Transaction::query()->count())->toBe(0)->and(Transaction::withTrashed()->count())->toBe(1);
});
