<?php

use App\Enums\FamilyRole;
use App\Enums\TransactionSource;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->owner = User::factory()->create(['name' => 'Ayah']);
    $this->family = createFamily($this->owner);
    $this->wallet = Wallet::query()->where('name', 'Tunai')->firstOrFail();
    $this->category = Category::query()->where('name', 'Transportasi')->firstOrFail();
});

function apiInput(array $overrides = []): array
{
    return [
        'type' => 'expense',
        'amount' => 50_000,
        'date' => '2026-10-31',
        'wallet_id' => test()->wallet->id,
        'category_id' => test()->category->id,
        'note' => 'bensin',
        'tags' => ['mudik'],
        ...$overrides,
    ];
}

it('requires a token', function () {
    $this->getJson('/api/v1/transactions')->assertUnauthorized();
});

it('creates a transaction and returns the resource [BR-B07]', function () {
    Sanctum::actingAs($this->owner, ['read', 'write']);

    $this->postJson('/api/v1/transactions', apiInput())
        ->assertCreated()
        ->assertJsonPath('data.amount', 50_000)
        ->assertJsonPath('data.amount_formatted', 'Rp 50.000')
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('data.wallet.name', 'Tunai')
        ->assertJsonPath('data.category.name', 'Transportasi')
        ->assertJsonPath('data.tags', ['mudik'])
        ->assertJsonPath('data.created_by.name', 'Ayah');

    expect(Transaction::query()->sole()->source)->toBe(TransactionSource::Api);
});

it('accepts free-form amounts like the web form [BR-A03] [BR-B12]', function () {
    Sanctum::actingAs($this->owner, ['read', 'write']);

    $this->postJson('/api/v1/transactions', apiInput(['amount' => '1,5jt']))
        ->assertCreated()
        ->assertJsonPath('data.amount', 1_500_000);
});

it('returns validation errors as JSON 422', function () {
    Sanctum::actingAs($this->owner, ['read', 'write']);

    $this->postJson('/api/v1/transactions', apiInput(['amount' => 0, 'date' => 'kemarin']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'date']);
});

it('returns business rule violations as JSON 422 with the field [BR-B02]', function () {
    Sanctum::actingAs($this->owner, ['read', 'write']);
    $salary = Category::query()->where('name', 'Gaji')->firstOrFail();

    $this->postJson('/api/v1/transactions', apiInput(['category_id' => $salary->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Jenis kategori tidak sesuai dengan jenis transaksi.')
        ->assertJsonValidationErrors(['category_id']);
});

it('enforces token abilities', function () {
    Sanctum::actingAs($this->owner, ['read']);

    $this->getJson('/api/v1/transactions')->assertOk();
    $this->postJson('/api/v1/transactions', apiInput())->assertForbidden();
});

it('paginates the list', function () {
    Sanctum::actingAs($this->owner, ['read', 'write']);
    Transaction::factory()->count(3)->expense(10_000, $this->wallet, $this->category)->create(['created_by' => $this->owner->id]);

    $this->getJson('/api/v1/transactions?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 2);
});

it('hides transactions of other families behind 404 [BR-I06]', function () {
    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');
    $theirs = Transaction::factory()
        ->expense(99_000, Wallet::query()->firstOrFail(), Category::query()->where('name', 'Transportasi')->firstOrFail())
        ->create(['created_by' => $neighbour->id]);

    Sanctum::actingAs($this->owner, ['read', 'write']);

    $this->getJson("/api/v1/transactions/{$theirs->id}")->assertNotFound();
    $this->putJson("/api/v1/transactions/{$theirs->id}", apiInput())->assertNotFound();
    $this->deleteJson("/api/v1/transactions/{$theirs->id}")->assertNotFound();
});

it('selects the family with X-Family-Id and rejects foreign families', function () {
    $second = createFamily($this->owner, 'Keluarga Kedua');
    $foreign = createFamily(name: 'Tetangga');
    Sanctum::actingAs($this->owner, ['read']);

    $this->withHeader('X-Family-Id', (string) $second->id)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.current_family_id', $second->id)
        ->assertJsonCount(2, 'data.families');

    $this->withHeader('X-Family-Id', (string) $foreign->id)->getJson('/api/v1/me')->assertNotFound();
});

it('applies the role matrix [BR-B09]', function () {
    $viewer = addMember($this->family, FamilyRole::Viewer);
    Sanctum::actingAs($viewer, ['read', 'write']);

    $this->getJson('/api/v1/transactions')->assertOk();
    $this->postJson('/api/v1/transactions', apiInput())->assertForbidden();
});

it('replays the first response for a repeated Idempotency-Key [BR-B13] [INV-11]', function () {
    Sanctum::actingAs($this->owner, ['read', 'write']);
    $headers = ['Idempotency-Key' => 'a3f1c2d4-0001'];

    $first = $this->postJson('/api/v1/transactions', apiInput(), $headers)->assertCreated();
    $second = $this->postJson('/api/v1/transactions', apiInput(), $headers)->assertCreated();

    $second->assertHeader('Idempotent-Replayed', 'true');
    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Transaction::query()->count())->toBe(1);

    $this->postJson('/api/v1/transactions', apiInput(), ['Idempotency-Key' => 'a3f1c2d4-0002'])->assertCreated();
    expect(Transaction::query()->count())->toBe(2);
});

it('stores identical rows from web and API input [BR-B12] [INV-01]', function () {
    $this->actingAs($this->owner)->post(route('transactions.store'), apiInput(['amount' => '50rb', 'tags' => 'mudik']));
    Sanctum::actingAs($this->owner, ['read', 'write']);
    $this->postJson('/api/v1/transactions', apiInput())->assertCreated();

    useFamily($this->family);
    $columns = ['type', 'amount', 'date', 'wallet_id', 'to_wallet_id', 'category_id', 'note', 'created_by', 'family_id'];
    [$web, $api] = Transaction::query()->with('tags')->orderBy('id')->get()->all();

    expect($web->only($columns))->toEqual($api->only($columns))
        ->and($web->tags->pluck('name'))->toEqual($api->tags->pluck('name'))
        ->and($web->source)->toBe(TransactionSource::Web)
        ->and($api->source)->toBe(TransactionSource::Api);
});
