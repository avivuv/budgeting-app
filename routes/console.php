<?php

use Illuminate\Support\Facades\Schedule;

// Keep activity log for ACTIVITYLOG_RETENTION_DAYS (default 30 days) only.

Schedule::command('activitylog:clean --force')->dailyAt('01:00')->onOneServer();

// Daily backup of both databases and uploaded files (docs/10-local-development.md, section 8).

Schedule::command('backup:clean')->dailyAt('01:30')->onOneServer();

Schedule::command('backup:run')->dailyAt('02:00')->onOneServer();
