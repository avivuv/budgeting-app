<?php

use App\Enums\FamilyRole;
use App\Models\Activity;
use App\Models\SavingGoal;
use App\Models\User;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/savings.md · Contract: docs/api-contracts/savings.md
|
| Golden family (Tunai 1.200.000, BCA 15.100.000); today 31 Oct 2026.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
});

function saverAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

function emergencyFund(int $saved = 0, int $target = 30_000_000): SavingGoal
{
    return SavingGoal::factory()->create([
        'family_id' => test()->golden->family->id,
        'name' => 'Dana Darurat',
        'target_amount' => $target,
        'saved_amount' => $saved,
    ]);
}

describe('positive', function () {
    it('[SAV-01] creates a goal typed as "30jt" with a deadline and reads back the monthly amount needed [BR-K01] [BR-K04]', function () {
        // GIVEN Ayah (owner)
        saverAs($this->golden->ayah);

        // WHEN he creates "Dana Darurat" for 30jt by the end of March 2027
        $response = $this->postJson('/api/v1/savings', ['name' => 'Dana Darurat', 'target_amount' => '30jt', 'deadline' => '2027-03-31']);

        // THEN the answer, the database and the list agree; Oct–Mar are 6 periods
        $response->assertCreated()
            ->assertJsonPath('data.name', 'Dana Darurat')
            ->assertJsonPath('data.target_amount', 30_000_000)
            ->assertJsonPath('data.target_formatted', 'Rp 30.000.000')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.remaining', 30_000_000)
            ->assertJsonPath('data.achieved', false)
            ->assertJsonPath('data.wallet_id', null)
            ->assertJsonPath('data.deadline', '2027-03-31')
            ->assertJsonPath('data.deadline_formatted', '31 Mar 2027')
            ->assertJsonPath('data.monthly_needed', 5_000_000)
            ->assertJsonPath('data.monthly_needed_formatted', 'Rp 5.000.000');

        $this->assertDatabaseHas('saving_goals', ['family_id' => $this->golden->family->id, 'name' => 'Dana Darurat', 'target_amount' => 30_000_000, 'saved_amount' => 0]);
        $this->getJson('/api/v1/savings')->assertOk()->assertJsonPath('data.0.name', 'Dana Darurat')->assertJsonPath('data.0.monthly_needed', 5_000_000);
    });

    it('[SAV-02] follows the balance of a linked wallet [BR-K01] [BR-K03]', function () {
        saverAs($this->golden->ayah);

        $this->postJson('/api/v1/savings', ['name' => 'Liburan', 'target_amount' => '1jt', 'wallet_id' => $this->golden->wallets['Tunai']->id])
            ->assertCreated()
            ->assertJsonPath('data.wallet_name', 'Tunai')
            ->assertJsonPath('data.progress', 1_200_000)
            ->assertJsonPath('data.percentage', 120)
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.achieved', true)
            ->assertJsonPath('data.monthly_needed', null);

        expect(SavingGoal::query()->sole()->achieved_at)->not->toBeNull();
    });

    it('[SAV-03] lets a member deposit "500rb" and withdraw "200rb" [BR-K02] [BR-B09]', function () {
        $goal = emergencyFund();
        saverAs($this->golden->ibu);

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => '500rb'])
            ->assertOk()
            ->assertJsonPath('message', 'Tabungan ditambahkan.')
            ->assertJsonPath('data.progress', 500_000);

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'withdraw', 'amount' => '200rb'])
            ->assertOk()
            ->assertJsonPath('message', 'Tabungan diambil.')
            ->assertJsonPath('data.progress', 300_000)
            ->assertJsonPath('data.remaining', 29_700_000);

        expect($goal->fresh()->saved_amount)->toBe(300_000);
        $this->getJson('/api/v1/savings')->assertJsonPath('data.0.progress', 300_000);
    });

    it('[SAV-04] deposits once when the same request is sent twice with an Idempotency-Key [BR-B13]', function () {
        $goal = emergencyFund();
        saverAs($this->golden->ibu);
        $headers = ['Idempotency-Key' => 'setor-dana-darurat-1'];

        $first = $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => 500_000], $headers)->assertOk();
        $second = $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => 500_000], $headers)->assertOk();

        expect($second->json())->toBe($first->json())
            ->and($goal->fresh()->saved_amount)->toBe(500_000);
    });

    it('[SAV-05] marks the goal reached and un-reached again [BR-K03]', function () {
        $goal = emergencyFund(saved: 900_000, target: 1_000_000);
        saverAs($this->golden->ayah);

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => 100_000])
            ->assertJsonPath('data.achieved', true)
            ->assertJsonPath('data.percentage', 100);
        expect($goal->fresh()->achieved_at)->not->toBeNull();

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'withdraw', 'amount' => 1])
            ->assertJsonPath('data.achieved', false);
        expect($goal->fresh()->achieved_at)->toBeNull();
    });

    it('[SAV-06] changes the name and target and drops the deadline', function () {
        $goal = emergencyFund(saved: 1_000_000);
        $goal->update(['deadline' => '2027-03-31']);
        saverAs($this->golden->ayah);

        $this->putJson("/api/v1/savings/{$goal->id}", ['name' => 'Dana Darurat Keluarga', 'target_amount' => '50jt', 'deadline' => null])
            ->assertOk()
            ->assertJsonPath('data.name', 'Dana Darurat Keluarga')
            ->assertJsonPath('data.target_amount', 50_000_000)
            ->assertJsonPath('data.progress', 1_000_000)
            ->assertJsonPath('data.deadline', null)
            ->assertJsonPath('data.monthly_needed', null);

        $this->assertDatabaseHas('saving_goals', ['id' => $goal->id, 'name' => 'Dana Darurat Keluarga', 'target_amount' => 50_000_000, 'deadline' => null]);
    });

    it('[SAV-07] deletes a goal without touching wallets, and logs it', function () {
        $goal = SavingGoal::factory()->create(['family_id' => $this->golden->family->id, 'wallet_id' => $this->golden->wallets['BCA']->id]);
        saverAs($this->golden->ayah);

        $this->deleteJson("/api/v1/savings/{$goal->id}")->assertNoContent();

        $this->getJson('/api/v1/savings')->assertJsonPath('data', []);
        $this->getJson('/api/v1/wallets')->assertJsonPath('meta.total_balance', GoldenFamily::EXPECTED['total_balance']);
        expect(Activity::query()->where('subject_type', (new SavingGoal)->getMorphClass())->where('subject_id', $goal->id)->where('event', 'deleted')->exists())->toBeTrue();
    });

    it('[SAV-08] shows members the goals with contribute-only rights', function () {
        emergencyFund(saved: 2_000_000);
        saverAs($this->golden->ibu, ['read']);

        $this->getJson('/api/v1/savings')
            ->assertOk()
            ->assertJsonPath('data.0.progress', 2_000_000)
            ->assertJsonPath('meta.can_manage', false)
            ->assertJsonPath('meta.can_contribute', true);
    });
});

describe('negative', function () {
    it('[SAV-21] rejects a missing name, a bad target or a bad deadline', function (array $input, string $field) {
        saverAs($this->golden->ayah);

        $this->postJson('/api/v1/savings', [...['name' => 'Dana Darurat', 'target_amount' => '30jt'], ...$input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(SavingGoal::query()->count())->toBe(0);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'no target' => [['target_amount' => ''], 'target_amount'],
        'zero target' => [['target_amount' => 0], 'target_amount'],
        'text target' => [['target_amount' => 'abc'], 'target_amount'],
        'bad deadline' => [['deadline' => '31-03-2027'], 'deadline'],
    ]);

    it('[SAV-22] refuses to withdraw more than was saved [BR-K02]', function () {
        $goal = emergencyFund(saved: 300_000);
        saverAs($this->golden->ibu);

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'withdraw', 'amount' => '500rb'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'Jumlah yang diambil melebihi tabungan yang terkumpul.')
            ->assertJsonPath('rule', 'BR-K02');

        expect($goal->fresh()->saved_amount)->toBe(300_000);
    });

    it('[SAV-23] refuses a manual deposit on a goal linked to a wallet [BR-K02]', function () {
        $goal = SavingGoal::factory()->create(['family_id' => $this->golden->family->id, 'wallet_id' => $this->golden->wallets['BCA']->id]);
        saverAs($this->golden->ayah);

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => 100_000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount')
            ->assertJsonPath('rule', 'BR-K02');

        expect($goal->fresh()->saved_amount)->toBe(0);
    });

    it('[SAV-24] rejects an unknown direction or a zero amount', function (array $input, string $field) {
        $goal = emergencyFund(saved: 100_000);
        saverAs($this->golden->ayah);

        $this->postJson("/api/v1/savings/{$goal->id}/contributions", [...['direction' => 'deposit', 'amount' => 1_000], ...$input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($goal->fresh()->saved_amount)->toBe(100_000);
    })->with([
        'direction' => [['direction' => 'gift'], 'direction'],
        'zero' => [['amount' => 0], 'amount'],
    ]);

    it('[SAV-25] does not let a member create, change or delete goals [BR-B09]', function () {
        $goal = emergencyFund();
        saverAs($this->golden->ibu);

        $this->postJson('/api/v1/savings', ['name' => 'Mobil', 'target_amount' => '100jt'])->assertForbidden();
        $this->putJson("/api/v1/savings/{$goal->id}", ['name' => 'Ganti', 'target_amount' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/savings/{$goal->id}")->assertForbidden();

        expect(SavingGoal::query()->pluck('name')->all())->toBe(['Dana Darurat']);
    });

    it('[SAV-26] does not let a viewer or a read-only token deposit [BR-B09]', function () {
        $goal = emergencyFund();
        $viewer = addMember($this->golden->family, FamilyRole::Viewer);

        foreach ([[$viewer, ['read', 'write']], [$this->golden->ibu, ['read']]] as [$user, $abilities]) {
            saverAs($user, $abilities);
            $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => 1_000])->assertForbidden();
        }

        expect($goal->fresh()->saved_amount)->toBe(0);
    });

    it('[SAV-27] treats wallets and goals of another family as not found [BR-I06]', function () {
        $neighbour = User::factory()->create();
        $other = createFamily($neighbour, 'Tetangga');
        $foreignWallet = Wallet::query()->withoutGlobalScopes()->where('family_id', $other->id)->firstOrFail();
        $goal = emergencyFund(saved: 100_000);

        saverAs($this->golden->ayah);
        $this->postJson('/api/v1/savings', ['name' => 'Curang', 'target_amount' => '1jt', 'wallet_id' => $foreignWallet->id])->assertNotFound();
        expect(SavingGoal::query()->count())->toBe(1);

        saverAs($neighbour);
        $this->postJson("/api/v1/savings/{$goal->id}/contributions", ['direction' => 'deposit', 'amount' => 1_000])->assertNotFound();
        $this->deleteJson("/api/v1/savings/{$goal->id}")->assertNotFound();
        $this->getJson('/api/v1/savings')->assertJsonPath('data', []);
        expect($goal->fresh()->saved_amount)->toBe(100_000);
    });
});
