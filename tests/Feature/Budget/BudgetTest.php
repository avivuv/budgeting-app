<?php

use App\DTOs\Transaction\TransactionData;
use App\Enums\BudgetStatus;
use App\Enums\FamilyRole;
use App\Enums\TransactionType;
use App\Exceptions\Budget\BudgetRuleException;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\BudgetThresholdNotification;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;
use App\Services\Transaction\TransactionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    Notification::fake();
    $this->golden = GoldenFamily::seed();
    $this->period = app(PeriodService::class)->current();
});

function budgets(): BudgetService
{
    return app(BudgetService::class);
}

function spend(int $amount, Category $category, string $date = '2026-10-31', ?User $by = null): Transaction
{
    $golden = test()->golden;

    return app(TransactionService::class)->create(new TransactionData(
        type: TransactionType::Expense,
        amount: $amount,
        date: CarbonImmutable::parse($date),
        walletId: $golden->wallets['Tunai']->id,
        categoryId: $category->id,
    ), $by ?? $golden->ibu);
}

it('measures the golden food budget including sub-categories [BR-F02] [BR-F03] [BR-F04]', function () {
    budgets()->set($this->golden->categories['Makanan']->id, $this->period, 800_000);

    $item = budgets()->overview($this->period)[0];

    expect($item->spent)->toBe(650_000)
        ->and($item->remaining)->toBe(150_000)
        ->and($item->percentage)->toBe(81.3)
        ->and($item->status)->toBe(BudgetStatus::Warning)
        ->and($item->dailyAllowance)->toBe(150_000);
});

it('only allows budgets on expense categories [BR-F01]', function () {
    expect(fn () => budgets()->set($this->golden->categories['Gaji']->id, $this->period, 1_000_000))
        ->toThrow(BudgetRuleException::class);
});

it('keeps one budget per category and period [BR-F01]', function () {
    $food = $this->golden->categories['Makanan']->id;

    budgets()->set($food, $this->period, 800_000);
    budgets()->set($food, $this->period, 900_000);

    expect(Budget::query()->count())->toBe(1)
        ->and(Budget::query()->value('amount'))->toBe(900_000);
});

it('sends the 80% alert exactly once per period [BR-F05]', function () {
    $food = $this->golden->categories['Makanan'];
    budgets()->set($food->id, $this->period, 800_000);

    Notification::assertSentTimes(BudgetThresholdNotification::class, 2); // Ayah and Ibu

    spend(10_000, $food);
    spend(10_000, $food);

    Notification::assertSentTimes(BudgetThresholdNotification::class, 2);
    expect(Budget::query()->first()->alert_80_sent_at)->not->toBeNull();
});

it('sends the 100% alert when the budget is used up [BR-F05]', function () {
    $food = $this->golden->categories['Makanan'];
    budgets()->set($food->id, $this->period, 800_000);
    spend(150_000, $food);

    Notification::assertSentTo($this->golden->ayah, BudgetThresholdNotification::class, fn ($n) => $n->threshold === 100);
    Notification::assertSentTimes(BudgetThresholdNotification::class, 4);
});

it('sends only the 100% alert when spending jumps past both thresholds [BR-F05]', function () {
    $food = $this->golden->categories['Makanan'];
    budgets()->set($food->id, $this->period, 600_000);

    Notification::assertSentTimes(BudgetThresholdNotification::class, 2);
    Notification::assertNotSentTo($this->golden->ayah, BudgetThresholdNotification::class, fn ($n) => $n->threshold === 80);
});

it('can alert again after spending drops below the threshold [BR-F05]', function () {
    $food = $this->golden->categories['Makanan'];
    budgets()->set($food->id, $this->period, 1_000_000);
    $big = spend(200_000, $food);

    Notification::assertSentTimes(BudgetThresholdNotification::class, 2);

    app(TransactionService::class)->delete($big);
    expect(Budget::query()->first()->alert_80_sent_at)->toBeNull();

    spend(200_000, $food);
    Notification::assertSentTimes(BudgetThresholdNotification::class, 4);
});

it('re-checks the old period when an edit moves a transaction to another month [BR-F05]', function () {
    $food = $this->golden->categories['Makanan'];
    budgets()->set($food->id, $this->period, 800_000);
    $restaurant = Transaction::query()->where('amount', 400_000)->firstOrFail();

    app(TransactionService::class)->update($restaurant, new TransactionData(
        type: TransactionType::Expense,
        amount: 400_000,
        date: CarbonImmutable::parse('2026-09-20'),
        walletId: $restaurant->wallet_id,
        categoryId: $restaurant->category_id,
    ));

    expect(Budget::query()->first()->alert_80_sent_at)->toBeNull();
});

it('copies last period only for categories without a budget [BR-F07]', function () {
    $previous = app(PeriodService::class)->previous($this->period);
    budgets()->set($this->golden->categories['Makanan']->id, $previous, 800_000);
    budgets()->set($this->golden->categories['Transportasi']->id, $previous, 300_000);
    budgets()->set($this->golden->categories['Makanan']->id, $this->period, 950_000);

    expect(budgets()->copyFromPrevious($this->period))->toBe(1)
        ->and(budgets()->copyFromPrevious($this->period))->toBe(0);

    $amounts = collect(budgets()->overview($this->period))->pluck('amount', 'categoryName')->all();
    expect($amounts)->toBe(['Makanan & Minuman' => 950_000, 'Transportasi' => 300_000]);
});

it('does not double count a sub-category budget in the totals', function () {
    budgets()->set($this->golden->categories['Makanan']->id, $this->period, 800_000);
    budgets()->set($this->golden->categories['Restoran']->id, $this->period, 500_000);

    $totals = budgets()->totals(budgets()->overview($this->period), $this->period);

    expect($totals['amount'])->toBe(800_000)
        ->and($totals['spent'])->toBe(650_000);
});

it('shows the budget page to members and lets owners manage it', function () {
    budgets()->set($this->golden->categories['Makanan']->id, $this->period, 800_000);

    $this->actingAs($this->golden->ibu)
        ->get(route('budgets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('budgets/Index')
            ->where('budgets.0.spent', 650_000)
            ->where('budgets.0.status', 'warning')
            ->where('totals.amount', 800_000)
            ->where('canManage', false));

    $this->actingAs($this->golden->ayah)
        ->post(route('budgets.store'), ['category_id' => $this->golden->categories['Transportasi']->id, 'amount' => '300rb'])
        ->assertRedirect(route('budgets.index', ['period' => '2026-10']));

    expect(Budget::query()->where('amount', 300_000)->exists())->toBeTrue();
});

it('does not let members or viewers change budgets', function () {
    $viewer = addMember($this->golden->family, FamilyRole::Viewer);

    foreach ([$this->golden->ibu, $viewer] as $user) {
        $this->actingAs($user)
            ->post(route('budgets.store'), ['category_id' => $this->golden->categories['Makanan']->id, 'amount' => 100_000])
            ->assertForbidden();
    }
});

it('plans one period ahead but not further', function () {
    $this->actingAs($this->golden->ayah)
        ->get(route('budgets.index', ['period' => '2027-03']))
        ->assertInertia(fn (Assert $page) => $page->where('period.key', '2026-11')->where('period.nextKey', null));
});

it('copies budgets from the web page [BR-F07]', function () {
    budgets()->set($this->golden->categories['Makanan']->id, app(PeriodService::class)->previous($this->period), 800_000);

    $this->actingAs($this->golden->ayah)
        ->post(route('budgets.copy'), ['period' => '2026-10'])
        ->assertRedirect();

    expect(Budget::query()->where('period', '2026-10-01')->count())->toBe(1);
});

it('hides budgets of other families [BR-I06]', function () {
    budgets()->set($this->golden->categories['Makanan']->id, $this->period, 800_000);
    $budget = Budget::query()->firstOrFail();

    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');

    $this->actingAs($neighbour)->delete(route('budgets.destroy', $budget))->assertNotFound();
    expect(budgets()->overview($this->period))->toBe([]);
});
