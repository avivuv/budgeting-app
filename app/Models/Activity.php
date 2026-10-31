<?php

declare(strict_types=1);

namespace App\Models;

use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Activity log entry stored in the separate "activity" database (budgeting_app_log).
 * Entries older than ACTIVITYLOG_RETENTION_DAYS (default 30) are purged daily.
 */
class Activity extends BaseActivity
{
    protected $connection = 'activity';
}
