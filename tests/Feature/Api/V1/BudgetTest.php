<?php

use App\Enums\FamilyRole;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use App\Notifications\BudgetThresholdNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/budgets.md · Contract: docs/api-contracts/budgets.md
|
| Golden family, October 2026 (today 31 Oct, one day left in the period):
| spending Makanan 650.000 (incl. sub-category Restoran 400.000), Transportasi 50.000.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    Notification::fake();
    $this->golden = GoldenFamily::seed();
    $this->food = $this->golden->categories['Makanan'];
    $this->transport = $this->golden->categories['Transportasi'];
});

function budgetAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

/**
 * @param  list<array{0: Category, 1: int|string}>  $rows
 */
function putBudgets(array $rows, ?string $period = '2026-10'): TestResponse
{
    return test()->putJson('/api/v1/budgets', array_filter([
        'period' => $period,
        'budgets' => array_map(fn (array $row): array => ['category_id' => $row[0]->id, 'amount' => $row[1]], $rows),
    ], fn ($value) => $value !== null));
}

/**
 * Category name => budget amount, as the budget page shows it.
 *
 * @return array<string, int>
 */
function budgetAmounts(string $period = '2026-10'): array
{
    return collect(test()->getJson('/api/v1/budgets?period='.$period)->assertOk()->json('data'))
        ->pluck('amount', 'category.name')
        ->all();
}

describe('positive', function () {
    it('[BUD-01] sets a food budget typed as "800rb" and reads back its realisation incl. sub-categories [BR-F01] [BR-F02] [BR-F03] [BR-F04] [BR-F06]', function () {
        // GIVEN Ayah (owner) and 650.000 spent on food this period, 400.000 of it at Restoran
        budgetAs($this->golden->ayah);

        // WHEN he sets the food budget to "800rb"
        $response = putBudgets([[$this->food, '800rb']]);

        // THEN the answer, the database and a fresh read all show the same numbers
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', '2026-10')
            ->assertJsonPath('data.0.category.name', 'Makanan & Minuman')
            ->assertJsonPath('data.0.category.is_sub_category', false)
            ->assertJsonPath('data.0.amount', 800_000)
            ->assertJsonPath('data.0.amount_formatted', 'Rp 800.000')
            ->assertJsonPath('data.0.spent', 650_000)
            ->assertJsonPath('data.0.remaining', 150_000)
            ->assertJsonPath('data.0.percentage', 81.3)
            ->assertJsonPath('data.0.status', 'warning')
            ->assertJsonPath('data.0.status_label', 'Waspada')
            ->assertJsonPath('data.0.daily_allowance', 150_000)
            ->assertJsonPath('data.0.daily_allowance_formatted', 'Rp 150.000')
            ->assertJsonPath('meta.period.key', '2026-10')
            ->assertJsonPath('meta.period.is_current', true)
            ->assertJsonPath('meta.period.next_key', '2026-11');

        $this->assertDatabaseHas('budgets', [
            'family_id' => $this->golden->family->id,
            'category_id' => $this->food->id,
            'period' => '2026-10-01',
            'amount' => 800_000,
        ]);

        $this->getJson('/api/v1/budgets')->assertJsonPath('data.0.spent', 650_000)->assertJsonPath('data.0.amount', 800_000);
    });

    it('[BUD-02] sets two budgets at once; totals add up and the categories leave the "add" list', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->transport, 100_000], [$this->food, '800rb']])
            ->assertOk()
            ->assertJsonPath('data.0.category.name', 'Makanan & Minuman')
            ->assertJsonPath('data.1.category.name', 'Transportasi')
            ->assertJsonPath('data.1.spent', 50_000)
            ->assertJsonPath('meta.totals.amount', 900_000)
            ->assertJsonPath('meta.totals.amount_formatted', 'Rp 900.000')
            ->assertJsonPath('meta.totals.spent', 700_000)
            ->assertJsonPath('meta.totals.remaining', 200_000)
            ->assertJsonPath('meta.totals.percentage', 77.8)
            ->assertJsonPath('meta.totals.status', 'safe')
            ->assertJsonPath('meta.totals.daily_allowance', 200_000)
            ->assertJsonPath('meta.can_manage', true);

        $available = collect($this->getJson('/api/v1/budgets')->json('meta.available_categories'));
        expect($available->pluck('id'))->not->toContain($this->food->id)->not->toContain($this->transport->id)
            ->and($available->firstWhere('name', 'Restoran'))->toMatchArray(['parent_name' => 'Makanan & Minuman'])
            ->and($available->pluck('name'))->not->toContain('Gaji');
    });

    it('[BUD-03] changes an existing budget instead of adding a second one [BR-F01]', function () {
        budgetAs($this->golden->ayah);
        putBudgets([[$this->food, 800_000]])->assertOk();

        putBudgets([[$this->food, '1jt']])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 1_000_000)
            ->assertJsonPath('data.0.status', 'safe');

        expect(Budget::query()->where('category_id', $this->food->id)->count())->toBe(1);
    });

    it('[BUD-04] shows a sub-category budget on its own without counting it twice in the totals [BR-F02]', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, 800_000], [$this->golden->categories['Restoran'], 300_000]])
            ->assertOk()
            ->assertJsonPath('data.1.category.name', 'Restoran')
            ->assertJsonPath('data.1.category.is_sub_category', true)
            ->assertJsonPath('data.1.spent', 400_000)
            ->assertJsonPath('data.1.status', 'over')
            ->assertJsonPath('meta.totals.amount', 800_000)
            ->assertJsonPath('meta.totals.spent', 650_000);
    });

    it('[BUD-05] marks an overspent budget "over" with a negative remainder and no daily allowance [BR-F03] [BR-F04] [BR-F06]', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->transport, '40rb']])
            ->assertOk()
            ->assertJsonPath('data.0.status', 'over')
            ->assertJsonPath('data.0.status_label', 'Lewat')
            ->assertJsonPath('data.0.remaining', -10_000)
            ->assertJsonPath('data.0.remaining_formatted', '-Rp 10.000')
            ->assertJsonPath('data.0.percentage', 125)
            ->assertJsonPath('data.0.daily_allowance', 0);
    });

    it('[BUD-06] copies last period only for categories without a budget, and copying again adds nothing [BR-F07]', function () {
        // GIVEN September had food and transport; October already has food at 1jt
        Budget::factory()->forCategory($this->food, '2026-09-01', 800_000)->create();
        Budget::factory()->forCategory($this->transport, '2026-09-01', 150_000)->create();
        Budget::factory()->forCategory($this->food, '2026-10-01', 1_000_000)->create();
        budgetAs($this->golden->ayah);

        // WHEN Ayah copies into October
        $this->postJson('/api/v1/budgets/copy', ['period' => '2026-10'])
            ->assertOk()
            ->assertJsonPath('message', '1 budget disalin dari periode lalu.')
            ->assertJsonPath('meta.copied', 1)
            ->assertJsonCount(2, 'data');

        // THEN transport is copied and food keeps October's own amount
        expect(budgetAmounts())->toBe(['Makanan & Minuman' => 1_000_000, 'Transportasi' => 150_000]);

        // AND copying again changes nothing
        $this->postJson('/api/v1/budgets/copy', ['period' => '2026-10'])
            ->assertOk()
            ->assertJsonPath('message', 'Tidak ada budget baru untuk disalin.')
            ->assertJsonPath('meta.copied', 0);
        expect(Budget::query()->where('period', '2026-10-01')->count())->toBe(2);
    });

    it('[BUD-07] does not copy a budget of an archived category [BR-F09]', function () {
        Budget::factory()->forCategory($this->food, '2026-09-01', 800_000)->create();
        Budget::factory()->forCategory($this->transport, '2026-09-01', 150_000)->create();
        $this->transport->update(['is_archived' => true]);
        budgetAs($this->golden->ayah);

        $this->postJson('/api/v1/budgets/copy')->assertOk()->assertJsonPath('meta.copied', 1);

        expect(budgetAmounts())->toBe(['Makanan & Minuman' => 800_000]);
    });

    it('[BUD-08] deletes a budget: it disappears, the category can be budgeted again, and it is logged [BR-B14]', function () {
        budgetAs($this->golden->ayah);
        $id = putBudgets([[$this->food, 800_000]])->json('data.0.id');

        $this->deleteJson("/api/v1/budgets/{$id}")->assertNoContent();

        $this->getJson('/api/v1/budgets')
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.totals.amount', 0);
        expect(collect($this->getJson('/api/v1/budgets')->json('meta.available_categories'))->pluck('id'))->toContain($this->food->id)
            ->and(Budget::query()->find($id))->toBeNull()
            ->and(Activity::query()->where('subject_type', (new Budget)->getMorphClass())->where('subject_id', $id)->where('event', 'deleted')->exists())->toBeTrue();
    });

    it('[BUD-09] plans next period; it has no spending yet and cannot go further [BR-F08]', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, '3jt']], '2026-11')
            ->assertOk()
            ->assertJsonPath('meta.period.key', '2026-11')
            ->assertJsonPath('meta.period.is_current', false)
            ->assertJsonPath('meta.period.next_key', null)
            ->assertJsonPath('data.0.spent', 0)
            ->assertJsonPath('data.0.status', 'safe')
            ->assertJsonPath('data.0.daily_allowance', 100_000);

        $this->assertDatabaseHas('budgets', ['category_id' => $this->food->id, 'period' => '2026-11-01', 'amount' => 3_000_000]);
        expect(budgetAmounts('2026-10'))->toBe([]);
    });

    it('[BUD-10] shows members the same budgets, without the right to manage them', function () {
        budgetAs($this->golden->ayah);
        putBudgets([[$this->food, 800_000]])->assertOk();

        budgetAs($this->golden->ibu, ['read']);
        $this->getJson('/api/v1/budgets?period=2026-10')
            ->assertOk()
            ->assertJsonPath('data.0.amount', 800_000)
            ->assertJsonPath('data.0.spent', 650_000)
            ->assertJsonPath('meta.can_manage', false);
    });

    it('[BUD-11] alerts every member once when a budget passes 80%, even if saved twice [BR-F05]', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, '700rb']])->assertOk()->assertJsonPath('data.0.percentage', 92.9);
        putBudgets([[$this->food, '700rb']])->assertOk();

        Notification::assertSentTo([$this->golden->ayah, $this->golden->ibu], BudgetThresholdNotification::class);
        Notification::assertSentTimes(BudgetThresholdNotification::class, 2);
        expect(Budget::query()->sole()->alert_80_sent_at)->not->toBeNull();
    });

    it('[BUD-12] still changes an existing budget after its category was archived [BR-F09]', function () {
        budgetAs($this->golden->ayah);
        putBudgets([[$this->transport, 100_000]])->assertOk();
        $this->transport->update(['is_archived' => true]);

        putBudgets([[$this->transport, 200_000]])
            ->assertOk()
            ->assertJsonPath('data.0.amount', 200_000)
            ->assertJsonPath('data.0.category.is_archived', true);
    });
});

describe('negative', function () {
    it('[BUD-21] rejects an empty, zero, negative or unreadable amount and saves nothing [BR-A02]', function (mixed $amount) {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, $amount]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budgets.0.amount');

        expect(Budget::query()->count())->toBe(0);
    })->with(['empty' => [''], 'zero' => [0], 'negative' => [-50_000], 'text' => ['abc']]);

    it('[BUD-22] rolls back the whole list when one row is an income category [BR-F01]', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, 800_000], [$this->golden->categories['Gaji'], 100_000]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budgets.1.category_id')
            ->assertJsonFragment(['budgets.1.category_id' => ['Budget hanya bisa dibuat untuk kategori pengeluaran.']])
            ->assertJsonPath('rule', 'BR-F01');

        expect(Budget::query()->count())->toBe(0);
    });

    it('[BUD-23] refuses a new budget for an archived category [BR-F09]', function () {
        $this->transport->update(['is_archived' => true]);
        budgetAs($this->golden->ayah);

        putBudgets([[$this->transport, 100_000]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budgets.0.category_id')
            ->assertJsonPath('rule', 'BR-F09');

        expect(Budget::query()->count())->toBe(0);
    });

    it('[BUD-24] refuses a period two months ahead or in a wrong format [BR-F08]', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, 800_000]], '2027-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period')
            ->assertJsonPath('message', 'Budget hanya bisa diatur sampai satu periode ke depan.')
            ->assertJsonPath('rule', 'BR-F08');
        $this->getJson('/api/v1/budgets?period=2027-01')->assertUnprocessable()->assertJsonPath('rule', 'BR-F08');
        $this->postJson('/api/v1/budgets/copy', ['period' => '2027-01'])->assertUnprocessable()->assertJsonPath('rule', 'BR-F08');

        foreach (['2026-13', 'okt'] as $period) {
            $this->getJson('/api/v1/budgets?period='.$period)
                ->assertUnprocessable()
                ->assertJsonPath('errors.period.0', 'Format periode tidak valid.');
            putBudgets([[$this->food, 800_000]], $period)->assertUnprocessable()->assertJsonValidationErrors('period');
        }

        expect(Budget::query()->count())->toBe(0);
    });

    it('[BUD-25] refuses the same category twice in one list', function () {
        budgetAs($this->golden->ayah);

        putBudgets([[$this->food, 800_000], [$this->food, 900_000]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budgets.1.category_id');

        expect(Budget::query()->count())->toBe(0);
    });

    it('[BUD-26] treats a category of another family as not found [BR-I06]', function () {
        $neighbour = User::factory()->create();
        $other = createFamily($neighbour, 'Tetangga');
        $foreign = Category::factory()->create(['family_id' => $other->id, 'type' => 'expense', 'name' => 'Belanja Tetangga']);
        budgetAs($this->golden->ayah);

        $this->putJson('/api/v1/budgets', ['period' => '2026-10', 'budgets' => [
            ['category_id' => $this->food->id, 'amount' => 800_000],
            ['category_id' => $foreign->id, 'amount' => 100_000],
        ]])->assertNotFound();

        expect(Budget::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('[BUD-27] does not let members, viewers or read-only tokens change budgets [BR-B09]', function () {
        $budget = Budget::factory()->forCategory($this->food, '2026-10-01', 800_000)->create();
        Budget::factory()->forCategory($this->transport, '2026-09-01', 100_000)->create();
        $viewer = addMember($this->golden->family, FamilyRole::Viewer);

        foreach ([[$this->golden->ibu, ['read', 'write']], [$viewer, ['read', 'write']], [$this->golden->ayah, ['read']]] as [$user, $abilities]) {
            budgetAs($user, $abilities);

            putBudgets([[$this->food, 1]])->assertForbidden();
            $this->postJson('/api/v1/budgets/copy')->assertForbidden();
            $this->deleteJson("/api/v1/budgets/{$budget->id}")->assertForbidden();
        }

        expect(budgetAmounts())->toBe(['Makanan & Minuman' => 800_000]);
    });

    it('[BUD-28] cannot delete a budget of another family [BR-I06]', function () {
        $budget = Budget::factory()->forCategory($this->food, '2026-10-01', 800_000)->create();
        $neighbour = User::factory()->create();
        createFamily($neighbour, 'Tetangga');
        budgetAs($neighbour);

        $this->deleteJson("/api/v1/budgets/{$budget->id}")->assertNotFound();

        expect(Budget::query()->withoutGlobalScopes()->find($budget->id))->not->toBeNull();
    });

    it('[BUD-29] requires 1 to 100 rows', function (int $rows) {
        budgetAs($this->golden->ayah);

        $this->putJson('/api/v1/budgets', ['period' => '2026-10', 'budgets' => array_fill(0, $rows, ['category_id' => $this->food->id, 'amount' => 1_000])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('budgets');

        expect(Budget::query()->count())->toBe(0);
    })->with(['none' => [0], 'too many' => [101]]);
});
