<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function scheduledCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->mapWithKeys(fn (Event $event) => [trim(str($event->command)->after('artisan')->replace(["'", '"'], '')) => $event->expression])
        ->all();
}

it('backs up both databases and uploaded files to the backups disk', function () {
    expect(config('backup.backup.source.databases'))->toBe(['mysql', 'activity'])
        ->and(config('backup.backup.source.files.include'))->toBe([storage_path('app/private')])
        ->and(config('backup.backup.destination.disks'))->toBe(['backups'])
        ->and(config('filesystems.disks.backups.root'))->toBe(storage_path('app/backups'));
});

it('schedules backup, backup cleanup and activity log retention daily', function () {
    expect(scheduledCommands())->toMatchArray([
        'activitylog:clean --force' => '0 1 * * *',
        'backup:clean' => '30 1 * * *',
        'backup:run' => '0 2 * * *',
    ]);
});
