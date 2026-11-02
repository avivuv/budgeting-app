<?php

use App\Notifications\BudgetThresholdNotification;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fixtures\GoldenFamily;

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    $this->golden = GoldenFamily::seed();
    // Real database notifications (queue is sync in tests).
    app(BudgetService::class)->set($this->golden->categories['Makanan']->id, app(PeriodService::class)->current(), 800_000);
});

it('stores budget alerts as web notifications with an unread badge [BR-F05]', function () {
    $this->actingAs($this->golden->ibu)
        ->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/Index')
            ->where('unreadNotifications', 1)
            ->where('notifications.0.title', 'Budget Makanan & Minuman sudah 80%')
            ->where('notifications.0.read', false)
            ->where('notifications.0.url', '/budgets?period=2026-10'));
});

it('marks one or all notifications as read', function () {
    $id = $this->golden->ibu->notifications()->value('id');

    $this->actingAs($this->golden->ibu)->post(route('notifications.read'), ['id' => $id])->assertRedirect();
    expect($this->golden->ibu->unreadNotifications()->count())->toBe(0);

    $this->actingAs($this->golden->ayah)->post(route('notifications.read'))->assertRedirect();
    expect($this->golden->ayah->unreadNotifications()->count())->toBe(0);
});

it('only lets users read their own notifications', function () {
    $ayahs = $this->golden->ayah->notifications()->value('id');

    $this->actingAs($this->golden->ibu)->post(route('notifications.read'), ['id' => $ayahs]);

    expect($this->golden->ayah->unreadNotifications()->count())->toBe(1);
});

it('emails the budget alert too', function () {
    Notification::fake();
    app(BudgetService::class)->set($this->golden->categories['Transportasi']->id, app(PeriodService::class)->current(), 50_000);

    Notification::assertSentTo(
        $this->golden->ayah,
        BudgetThresholdNotification::class,
        fn ($notification, array $channels) => in_array('mail', $channels, true) && $notification->threshold === 100,
    );
});
