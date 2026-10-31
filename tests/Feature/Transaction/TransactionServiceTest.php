<?php

use App\DTOs\Transaction\TransactionData;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Exceptions\Transaction\TransactionRuleException;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Transaction\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->owner = User::factory()->create();
    $this->family = createFamily($this->owner);
    $this->wallet = Wallet::query()->where('name', 'Tunai')->firstOrFail();
    $this->expenseCategory = Category::query()->where('name', 'Transportasi')->firstOrFail();
    $this->incomeCategory = Category::query()->where('name', 'Gaji')->firstOrFail();
    $this->service = app(TransactionService::class);
});

function txData(array $overrides = []): TransactionData
{
    return TransactionData::from([
        'type' => 'expense',
        'amount' => 50_000,
        'date' => '2026-10-31',
        'wallet_id' => test()->wallet->id,
        'category_id' => test()->expenseCategory->id,
        ...$overrides,
    ]);
}

it('records an expense with author, source and family [BR-B07]', function () {
    $transaction = $this->service->create(txData(['note' => '  bensin  ', 'tags' => ['#Mudik', 'mudik', ' ']]), $this->owner);

    expect($transaction->family_id)->toBe($this->family->id)
        ->and($transaction->created_by)->toBe($this->owner->id)
        ->and($transaction->source)->toBe(TransactionSource::Web)
        ->and($transaction->amount)->toBe(50_000)
        ->and($transaction->note)->toBe('bensin')
        ->and($transaction->tags()->pluck('name')->all())->toBe(['mudik']);
});

it('requires a category of the same type [BR-B02]', function (array $overrides) {
    $this->service->create(txData($overrides), $this->owner);
})->with([
    'no category' => [['category_id' => null]],
    'income category on expense' => [fn () => ['category_id' => test()->incomeCategory->id]],
    'expense category on income' => [fn () => ['type' => 'income']],
])->throws(TransactionRuleException::class);

it('stores transfers without a category [BR-B03]', function () {
    $bank = Wallet::factory()->for($this->family)->create(['name' => 'BCA']);

    $transfer = $this->service->create(txData([
        'type' => 'transfer',
        'to_wallet_id' => $bank->id,
        'category_id' => $this->expenseCategory->id,
    ]), $this->owner);

    expect($transfer->type)->toBe(TransactionType::Transfer)
        ->and($transfer->category_id)->toBeNull()
        ->and($transfer->to_wallet_id)->toBe($bank->id);
});

it('rejects invalid transfers [BR-B03]', function (array $overrides) {
    $this->service->create(txData(['type' => 'transfer', ...$overrides]), $this->owner);
})->with([
    'no destination' => [['to_wallet_id' => null]],
    'same wallet' => [fn () => ['to_wallet_id' => test()->wallet->id]],
])->throws(TransactionRuleException::class);

it('rejects wallets and categories of another family [BR-B04] [BR-I06]', function (string $field) {
    $foreignFamily = createFamily(name: 'Tetangga');
    $foreignWallet = Wallet::query()->firstOrFail();
    $foreignCategory = Category::query()->where('name', 'Transportasi')->firstOrFail();
    useFamily($this->family);

    $this->service->create(txData([$field => $field === 'wallet_id' ? $foreignWallet->id : $foreignCategory->id]), $this->owner);
})->with(['wallet_id', 'category_id'])->throws(ModelNotFoundException::class);

it('blocks archived wallets for new transactions but keeps old ones editable [BR-B05]', function () {
    $transaction = $this->service->create(txData(), $this->owner);
    $this->wallet->update(['is_archived' => true]);

    $updated = $this->service->update($transaction, txData(['amount' => 60_000]));
    expect($updated->amount)->toBe(60_000);

    expect(fn () => $this->service->create(txData(), $this->owner))->toThrow(TransactionRuleException::class);
});

it('accepts dates from 5 years ago up to today in the family timezone [BR-B06]', function (string $date, bool $valid) {
    $call = fn () => $this->service->create(txData(['date' => $date]), $this->owner);

    $valid ? expect($call())->toBeInstanceOf(Transaction::class) : expect($call)->toThrow(TransactionRuleException::class);
})->with([
    'today' => ['2026-10-31', true],
    'tomorrow' => ['2026-11-01', false],
    'exactly 5 years' => ['2021-10-31', true],
    'older than 5 years' => ['2021-10-30', false],
]);

it('uses the family timezone for "today" [BR-B06] [BR-D03]', function () {
    // 31 Oct 20:00 UTC is already 1 Nov 03:00 in Jakarta.
    $this->travelTo(CarbonImmutable::parse('2026-10-31 20:00:00', 'UTC'));

    expect($this->service->create(txData(['date' => '2026-11-01']), $this->owner)->date->toDateString())->toBe('2026-11-01');
});

it('rejects out of range amounts [BR-A02]', function (int $amount) {
    $this->service->create(txData(['amount' => $amount]), $this->owner);
})->with([0, -1, 1_000_000_000_000])->throws(TransactionRuleException::class);

it('keeps source and author on update [BR-B07]', function () {
    $transaction = $this->service->create(txData(['source' => 'telegram']), $this->owner);

    $updated = $this->service->update($transaction, txData(['amount' => 75_000, 'source' => 'web']));

    expect($updated->source)->toBe(TransactionSource::Telegram)
        ->and($updated->created_by)->toBe($this->owner->id);
});

it('soft deletes transactions [BR-B08]', function () {
    $transaction = $this->service->create(txData(), $this->owner);
    $this->service->delete($transaction);

    expect(Transaction::query()->count())->toBe(0)
        ->and(Transaction::withTrashed()->count())->toBe(1);
});

it('writes old and new values to the activity log [BR-B14] [INV-13]', function () {
    $this->actingAs($this->owner);
    $transaction = $this->service->create(txData(), $this->owner);
    $this->service->update($transaction, txData(['amount' => 45_000]));

    $update = Activity::query()->where('subject_type', $transaction->getMorphClass())->where('event', 'updated')->sole();

    expect($update->getAttribute('family_id'))->toBe($this->family->id)
        ->and($update->causer_id)->toBe($this->owner->id)
        ->and($update->attribute_changes['old']['amount'])->toBe(50_000)
        ->and($update->attribute_changes['attributes']['amount'])->toBe(45_000);
});
