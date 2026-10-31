<?php

use Illuminate\Support\Facades\Schedule;

// Keep activity log for ACTIVITYLOG_RETENTION_DAYS (default 30 days) only.
Schedule::command('activitylog:clean --force')->dailyAt('01:00')->onOneServer();
