<?php

use App\Models\User;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/notifications.md · Contract: docs/api-contracts/notifications.md
|
| Golden family; a food budget of 800.000 with 650.000 spent (81,3%) sends the
| 80% alert to Ayah and Ibu (database notifications, queue sync in tests).
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
    app(BudgetService::class)->set($this->golden->categories['Makanan']->id, app(PeriodService::class)->current(), 800_000);
});

function notifiedAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

describe('positive', function () {
    it('[NOT-01] shows the 80% budget alert with what it is about [BR-F05]', function () {
        notifiedAs($this->golden->ibu);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.kind', 'budget_threshold')
            ->assertJsonPath('data.0.title', 'Budget Makanan & Minuman sudah 80%')
            ->assertJsonPath('data.0.family_id', $this->golden->family->id)
            ->assertJsonPath('data.0.subject.period', '2026-10')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('data.0.created_at', '2026-10-31T12:00:00+07:00')
            ->assertJsonPath('meta.unread_count', 1);
    });

    it('[NOT-02] marks one notification as read, only for that user', function () {
        notifiedAs($this->golden->ibu);
        $id = $this->getJson('/api/v1/notifications')->json('data.0.id');

        $this->postJson('/api/v1/notifications/read', ['id' => $id])
            ->assertOk()
            ->assertJsonPath('meta.unread_count', 0);

        $this->getJson('/api/v1/notifications')->assertJsonPath('data.0.read', true);
        expect($this->golden->ayah->unreadNotifications()->count())->toBe(1);
    });

    it('[NOT-03] marks all notifications as read', function () {
        notifiedAs($this->golden->ayah);

        $this->postJson('/api/v1/notifications/read')->assertOk()->assertJsonPath('meta.unread_count', 0);

        expect($this->golden->ayah->unreadNotifications()->count())->toBe(0);
    });

    it('[NOT-04] works for a user without a family', function () {
        notifiedAs(User::factory()->create());

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.unread_count', 0);
    });
});

describe('negative', function () {
    it('[NOT-21] does not touch a notification of another user', function () {
        $ayahs = $this->golden->ayah->notifications()->value('id');
        notifiedAs($this->golden->ibu);

        $this->postJson('/api/v1/notifications/read', ['id' => $ayahs])->assertNotFound();

        expect($this->golden->ayah->unreadNotifications()->count())->toBe(1)
            ->and($this->golden->ibu->unreadNotifications()->count())->toBe(1);
    });

    it('[NOT-22] rejects an id that is not a uuid', function () {
        notifiedAs($this->golden->ibu);

        $this->postJson('/api/v1/notifications/read', ['id' => '123'])->assertUnprocessable()->assertJsonValidationErrors('id');
    });

    it('[NOT-23] needs the write ability to mark as read, and a signed-in user to read', function () {
        notifiedAs($this->golden->ibu, ['read']);
        $this->postJson('/api/v1/notifications/read')->assertForbidden();
        expect($this->golden->ibu->unreadNotifications()->count())->toBe(1);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    });
});
