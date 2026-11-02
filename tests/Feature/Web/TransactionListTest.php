<?php

use App\Enums\FamilyRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

it('lists the newest transactions first with totals for the whole filter [BR-B08] [BR-B11]', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transactions/Index')
            ->has('transactions.data', 7)
            ->where('transactions.data.0.date', '2026-10-20')
            ->where('aggregates.count', 7)
            ->where('aggregates.income', GoldenFamily::EXPECTED['income'])
            ->where('aggregates.expense', GoldenFamily::EXPECTED['expense'])
            ->has('options.members', 2)
            ->has('options.sources', 5));
});

it('adds up daily subtotals to the filtered total [INV-06]', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['type' => 'expense']))
        ->assertInertia(function (Assert $page) {
            $daily = collect($page->toArray()['props']['daily']);

            expect($daily->sum('expense'))->toBe(GoldenFamily::EXPECTED['expense'])
                ->and($daily->sum('expense'))->toBe($page->toArray()['props']['aggregates']['expense'])
                ->and($daily['2026-10-03']['expenseFormatted'])->toBe('Rp 250.000');
        });
});

it('filters by date range, type, wallet, member, amount and search', function (array $query, int $count, int $expense) {
    // Ids depend on the seeded golden family, so the dataset passes resolvers.
    $golden = $this->golden;
    array_walk_recursive($query, function (&$value) use ($golden) {
        $value = $value instanceof Closure ? $value($golden) : $value;
    });

    $this->actingAs($golden->ayah)
        ->get(route('transactions.index', $query))
        ->assertInertia(fn (Assert $page) => $page
            ->where('aggregates.count', $count)
            ->where('aggregates.expense', $expense));
})->with([
    'date range [BR-D04]' => [['from' => '2026-10-03', 'to' => '2026-10-10'], 3, 1_837_500],
    'type' => [['type' => 'expense'], 5, 2_287_500],
    'wallet incl. transfers in' => [['wallet_id' => [fn ($g) => $g->wallets['Tunai']->id]], 3, 300_000],
    'member' => [['user_id' => [fn ($g) => $g->ibu->id]], 3, 737_500],
    'min/max typed as "100rb"' => [['min' => '100rb', 'max' => '1jt'], 3, 650_000],
    'source' => [['source' => 'telegram'], 0, 0],
]);

it('includes sub-categories when filtering by a parent category [BR-E04]', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['category_id' => [$this->golden->categories['Makanan']->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('aggregates.count', 2)
            ->where('aggregates.expense', GoldenFamily::EXPECTED['food_including_restaurant']));
});

it('searches notes and merchants', function () {
    Transaction::factory()->on('2026-10-30')
        ->expense(35_000, $this->golden->wallets['Tunai'], $this->golden->categories['Makanan'])
        ->create(['note' => 'Bakso 100% enak', 'created_by' => $this->golden->ibu->id]);

    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['q' => '100%']))
        ->assertInertia(fn (Assert $page) => $page->where('aggregates.count', 1)->where('filters.q', '100%'));
});

it('filters by tag', function () {
    Transaction::factory()->on('2026-10-30')
        ->expense(35_000, $this->golden->wallets['Tunai'], $this->golden->categories['Makanan'])
        ->create(['created_by' => $this->golden->ibu->id])
        ->tags()->create(['name' => 'liburan', 'family_id' => $this->golden->family->id]);

    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['tag' => ['#Liburan']]))
        ->assertInertia(fn (Assert $page) => $page->where('aggregates.count', 1));
});

it('sorts by a whitelisted column only', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['sort' => '-amount']))
        ->assertInertia(fn (Assert $page) => $page->where('transactions.data.0.amount', 8_000_000));

    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['sort' => 'note; drop table']))
        ->assertSessionHasErrors('sort');
});

it('rejects an end date before the start date', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('transactions.index', ['from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});

it('returns the same filtered totals through the REST API [INV-03]', function () {
    Sanctum::actingAs($this->golden->ayah, ['read']);

    $this->getJson('/api/v1/transactions?type=expense&user_id[]='.$this->golden->ibu->id)
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.totals.expense', 737_500)
        ->assertJsonPath('meta.totals.income', 0);
});

it('deletes the selected transactions in bulk [BR-B08] [BR-B14]', function () {
    $ids = Transaction::query()->where('created_by', $this->golden->ibu->id)->pluck('id')->all();

    $this->actingAs($this->golden->ayah)
        ->post(route('transactions.bulk'), ['action' => 'delete', 'ids' => $ids])
        ->assertRedirect();

    expect(Transaction::query()->whereIn('id', $ids)->count())->toBe(0)
        ->and(Transaction::withTrashed()->whereIn('id', $ids)->count())->toBe(3);
});

it('changes the category of the selected transactions in bulk [BR-B02] [BR-B14]', function () {
    $transport = $this->golden->categories['Transportasi'];
    $ids = Transaction::query()->where('created_by', $this->golden->ibu->id)->pluck('id')->all();

    $this->actingAs($this->golden->ayah)
        ->post(route('transactions.bulk'), ['action' => 'category', 'ids' => $ids, 'category_id' => $transport->id])
        ->assertRedirect();

    expect(Transaction::query()->whereIn('id', $ids)->pluck('category_id')->unique()->all())->toBe([$transport->id]);
});

it('refuses a category of the wrong type or a transfer in bulk [BR-B02] [BR-B03]', function () {
    $income = Transaction::query()->where('type', 'income')->pluck('id')->all();
    $transfer = Transaction::query()->where('type', 'transfer')->pluck('id')->all();
    $food = $this->golden->categories['Makanan']->id;

    $this->actingAs($this->golden->ayah)
        ->post(route('transactions.bulk'), ['action' => 'category', 'ids' => $income, 'category_id' => $food])
        ->assertSessionHasErrors('category_id');

    $this->actingAs($this->golden->ayah)
        ->post(route('transactions.bulk'), ['action' => 'category', 'ids' => $transfer, 'category_id' => $food])
        ->assertSessionHasErrors('category_id');
});

it('lets members bulk-change only their own transactions [BR-B09]', function () {
    $ayahs = Transaction::query()->where('created_by', $this->golden->ayah->id)->pluck('id')->all();

    $this->actingAs($this->golden->ibu)
        ->post(route('transactions.bulk'), ['action' => 'delete', 'ids' => $ayahs])
        ->assertForbidden();

    expect(Transaction::query()->whereIn('id', $ayahs)->count())->toBe(count($ayahs));
});

it('does not let viewers use bulk actions [BR-B09]', function () {
    $viewer = addMember($this->golden->family, FamilyRole::Viewer);

    $this->actingAs($viewer)
        ->post(route('transactions.bulk'), ['action' => 'delete', 'ids' => [Transaction::query()->value('id')]])
        ->assertForbidden();
});

it('answers 404 for transactions of another family in bulk [BR-I06]', function () {
    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');
    $theirs = Transaction::factory()
        ->expense(99_000, Wallet::query()->firstOrFail(), Category::query()->where('name', 'Transportasi')->firstOrFail())
        ->create(['created_by' => $neighbour->id]);

    $this->actingAs($this->golden->ayah)
        ->post(route('transactions.bulk'), ['action' => 'delete', 'ids' => [$theirs->id]])
        ->assertNotFound();
});
