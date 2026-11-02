<?php

use App\DTOs\Savings\SavingGoalData;
use App\DTOs\Transaction\TransactionData;
use App\Enums\FamilyRole;
use App\Enums\TransactionType;
use App\Exceptions\Savings\SavingGoalRuleException;
use App\Models\SavingGoal;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Savings\SavingGoalService;
use App\Services\Transaction\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

function goals(): SavingGoalService
{
    return app(SavingGoalService::class);
}

it('tracks a manual goal through deposits and withdrawals [BR-K01] [BR-K02] [BR-K03]', function () {
    $goal = goals()->create(new SavingGoalData(name: 'Liburan', targetAmount: 3_000_000, deadline: '2027-03-15'));

    goals()->contribute($goal, 1_000_000);
    goals()->contribute($goal->refresh(), -200_000);

    $item = goals()->overview()[0];

    expect($item->progress)->toBe(800_000)
        ->and($item->remaining)->toBe(2_200_000)
        ->and($item->percentage)->toBe(26.7)
        ->and($item->achieved)->toBeFalse()
        // Oct … Mar = 6 periods: ceil(2.200.000 / 6) = 366.667 (BR-K04).
        ->and($item->monthlyNeeded)->toBe(366_667);

    expect(fn () => goals()->contribute($goal->refresh(), -900_000))->toThrow(SavingGoalRuleException::class);
});

it('follows the linked wallet balance and refuses manual money [BR-K01] [BR-K02]', function () {
    $goal = goals()->create(new SavingGoalData(name: 'Dana darurat', targetAmount: 1_000_000, walletId: $this->golden->wallets['Tunai']->id));

    expect(goals()->overview()[0]->progress)->toBe(1_200_000)
        ->and(goals()->overview()[0]->achieved)->toBeTrue()
        ->and($goal->refresh()->achieved_at)->not->toBeNull();

    expect(fn () => goals()->contribute($goal, 100_000))->toThrow(SavingGoalRuleException::class);
});

it('clears achieved when the linked wallet drops below the target again [BR-K03]', function () {
    $tunai = $this->golden->wallets['Tunai'];
    $goal = goals()->create(new SavingGoalData(name: 'Dana darurat', targetAmount: 1_000_000, walletId: $tunai->id));
    expect($goal->refresh()->achieved_at)->not->toBeNull();

    app(TransactionService::class)->create(new TransactionData(
        type: TransactionType::Expense,
        amount: 500_000,
        date: CarbonImmutable::parse('2026-10-31'),
        walletId: $tunai->id,
        categoryId: $this->golden->categories['Belanja']->id,
    ), $this->golden->ayah);

    expect($goal->refresh()->achieved_at)->toBeNull()
        ->and(goals()->overview()[0]->progress)->toBe(700_000);
});

it('rejects a target of zero or a wallet of another family [BR-K01] [BR-I06]', function () {
    expect(fn () => goals()->create(new SavingGoalData(name: 'X', targetAmount: 0)))->toThrow(SavingGoalRuleException::class);

    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');
    $theirWallet = Wallet::query()->firstOrFail();
    useFamily($this->golden->family);

    expect(fn () => goals()->create(new SavingGoalData(name: 'X', targetAmount: 1, walletId: $theirWallet->id)))
        ->toThrow(ModelNotFoundException::class);
});

it('manages goals from the web page with role checks [BR-B09]', function () {
    $this->actingAs($this->golden->ayah)
        ->post(route('savings.store'), ['name' => 'Laptop', 'target_amount' => '15jt', 'deadline' => '2027-06-30'])
        ->assertRedirect(route('savings.index'));

    $goal = SavingGoal::query()->sole();
    expect($goal->target_amount)->toBe(15_000_000);

    $this->actingAs($this->golden->ibu)
        ->post(route('savings.contribute', $goal), ['direction' => 'deposit', 'amount' => '500rb'])
        ->assertRedirect();
    $this->actingAs($this->golden->ibu)
        ->put(route('savings.update', $goal), ['name' => 'Laptop', 'target_amount' => 1])
        ->assertForbidden();

    $viewer = addMember($this->golden->family, FamilyRole::Viewer);
    $this->actingAs($viewer)
        ->post(route('savings.contribute', $goal), ['direction' => 'deposit', 'amount' => 1_000])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->get(route('savings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('savings/Index')
            ->where('goals.0.progress', 500_000)
            ->where('goals.0.targetFormatted', 'Rp 15.000.000')
            ->where('canManage', false)
            ->where('canContribute', false));
});

it('rejects taking out more than was saved [BR-K02]', function () {
    $goal = goals()->create(new SavingGoalData(name: 'Liburan', targetAmount: 3_000_000));

    $this->actingAs($this->golden->ayah)
        ->post(route('savings.contribute', $goal), ['direction' => 'withdraw', 'amount' => 1_000])
        ->assertSessionHasErrors('amount');
});
