<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Transaction\TransactionCreated;
use App\Events\Transaction\TransactionDeleted;
use App\Events\Transaction\TransactionUpdated;
use App\Services\Budget\BudgetService;
use App\Services\Report\PeriodService;

/**
 * BR-F05: after a transaction changes, re-check the budgets of its period
 * (and of its old period when an edit moved the date).
 */
class CheckBudgetThresholds
{
    public function __construct(
        private readonly BudgetService $budgets,
        private readonly PeriodService $periods,
    ) {}

    public function handle(TransactionCreated|TransactionUpdated|TransactionDeleted $event): void
    {
        $dates = [$event->transaction->date];

        if ($event instanceof TransactionUpdated && $event->previousDate !== null) {
            $dates[] = $event->previousDate;
        }

        $checked = [];
        foreach ($dates as $date) {
            $period = $this->periods->containing($date);

            if (! isset($checked[$period->key()])) {
                $checked[$period->key()] = true;
                $this->budgets->checkThresholds($period);
            }
        }
    }
}
