<?php

use App\Models\Family;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
| Scenarios: docs/test-cases/onboarding.md · Contract: docs/api-contracts/onboarding.md
|
| Budi just registered and has no family yet. Today 31 Oct 2026.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->budi = User::factory()->create(['name' => 'Budi Santoso']);
    Sanctum::actingAs($this->budi, ['read', 'write']);
});

describe('positive', function () {
    it('[ONB-01] tells a user without a family to create one first', function () {
        $this->getJson('/api/v1/wallets')
            ->assertStatus(409)
            ->assertJsonPath('code', 'no_family');
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.current_family_id', null)->assertJsonPath('data.families', []);
    });

    it('[ONB-02] creates "Keluarga Budi" with the default wallet and categories; Budi is its owner [BR-I01]', function () {
        // WHEN Budi creates his family
        $response = $this->postJson('/api/v1/families', ['name' => '  Keluarga Budi ']);

        // THEN it is active, he owns it, and it is ready to use
        $family = Family::query()->sole();
        $response->assertCreated()
            ->assertJsonPath('message', 'Keluarga Budi siap digunakan.')
            ->assertJsonPath('data.current_family_id', $family->id)
            ->assertJsonPath('data.families.0.name', 'Keluarga Budi')
            ->assertJsonPath('data.families.0.role', 'owner');

        expect($family->period_start_day)->toBe(1)->and($family->timezone)->toBe('Asia/Jakarta');

        $this->getJson('/api/v1/wallets')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Tunai')
            ->assertJsonPath('meta.total_balance', 0);
        $categories = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'));
        expect($categories->where('type', 'expense')->pluck('name'))->toContain('Makanan & Minuman')
            ->and($categories->where('type', 'income')->pluck('name'))->toContain('Gaji');
        $this->getJson('/api/v1/summary')->assertJsonPath('data.period.start', '2026-10-01')->assertJsonPath('data.period.end', '2026-10-31');
    });

    it('[ONB-03] starts the monthly period on the chosen day [BR-D01] [BR-D02]', function () {
        $this->postJson('/api/v1/families', ['name' => 'Keluarga Budi', 'period_start_day' => 25])->assertCreated();

        $this->getJson('/api/v1/summary')
            ->assertOk()
            ->assertJsonPath('data.period.key', '2026-11')
            ->assertJsonPath('data.period.start', '2026-10-25')
            ->assertJsonPath('data.period.end', '2026-11-24');
    });

    it('[ONB-04] creates one family when the same request is sent twice [BR-B13]', function () {
        $headers = ['Idempotency-Key' => 'buat-keluarga-budi'];

        $first = $this->postJson('/api/v1/families', ['name' => 'Keluarga Budi'], $headers)->assertCreated();
        $second = $this->postJson('/api/v1/families', ['name' => 'Keluarga Budi'], $headers)->assertCreated();

        expect($second->json())->toBe($first->json())
            ->and(Family::query()->count())->toBe(1);
    });

    it('[ONB-05] makes a second family active and keeps the first', function () {
        $first = $this->postJson('/api/v1/families', ['name' => 'Keluarga Budi'])->json('data.current_family_id');

        $response = $this->postJson('/api/v1/families', ['name' => 'Usaha Warung'])->assertCreated();

        expect($response->json('data.current_family_id'))->not->toBe($first)
            ->and(collect($response->json('data.families'))->pluck('name')->all())->toEqualCanonicalizing(['Keluarga Budi', 'Usaha Warung']);
    });
});

describe('negative', function () {
    it('[ONB-21] requires a name of at most 100 characters', function (string $name) {
        $this->postJson('/api/v1/families', ['name' => $name])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        expect(Family::query()->count())->toBe(0);
    })->with(['empty' => [''], 'too long' => [str_repeat('a', 101)]]);

    it('[ONB-22] accepts a period start day between 1 and 28 only', function (mixed $day) {
        $this->postJson('/api/v1/families', ['name' => 'Keluarga Budi', 'period_start_day' => $day])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('period_start_day');

        expect(Family::query()->count())->toBe(0);
    })->with([[0], [29], ['abc']]);

    it('[ONB-23] needs the write ability and a signed-in user', function () {
        Sanctum::actingAs($this->budi, ['read']);
        $this->postJson('/api/v1/families', ['name' => 'Keluarga Budi'])->assertForbidden();

        auth()->forgetGuards();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->postJson('/api/v1/families', ['name' => 'Keluarga Budi'])->assertUnauthorized();

        expect(Family::query()->count())->toBe(0);
    });
});
