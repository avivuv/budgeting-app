<?php

use App\Models\Activity;
use Illuminate\Support\Facades\Schema;

it('stores activity log in the separate activity database', function () {
    activity()->log('created transaction');

    expect(Activity::query()->count())->toBe(1)
        ->and((new Activity)->getConnectionName())->toBe('activity')
        ->and(Schema::connection('activity')->getConnection()->getDatabaseName())->toBe('budgeting_app_log_test')
        ->and(Schema::connection('mysql')->hasTable('activity_log'))->toBeFalse();
});

it('purges activity log entries older than the 30 day retention', function () {
    $this->travelTo(now()->subDays(31));
    activity()->log('old entry');

    $this->travelBack();
    $this->travelTo(now()->subDays(29));
    activity()->log('recent entry');

    $this->travelBack();
    $this->artisan('activitylog:clean', ['--force' => true])->assertSuccessful();

    expect(Activity::query()->pluck('description')->all())->toBe(['recent entry']);
});

it('uses 30 days as the default retention', function () {
    expect(config('activitylog.clean_after_days'))->toBe(30);
});
