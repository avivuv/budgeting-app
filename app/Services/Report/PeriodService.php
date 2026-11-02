<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Services\Tenancy\TenantContext;
use App\Support\Period\Period;
use App\Support\Period\PeriodResolver;
use Carbon\CarbonImmutable;

/**
 * The active family's calendar: today in its timezone (BR-D03) and monthly
 * periods starting on its period_start_day (BR-D01, BR-D02).
 */
class PeriodService
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->tenant->get()->timezone)->startOfDay();
    }

    public function current(): Period
    {
        return $this->containing($this->today());
    }

    public function containing(CarbonImmutable $date): Period
    {
        return PeriodResolver::containing($date, $this->tenant->get()->period_start_day);
    }

    public function forMonth(int $year, int $month): Period
    {
        return PeriodResolver::forMonth($year, $month, $this->tenant->get()->period_start_day);
    }

    /**
     * The period for a "YYYY-MM" key; missing, invalid or future keys give the current period.
     */
    public function fromKey(?string $key, bool $allowFuture = false): Period
    {
        $current = $this->current();

        if ($key === null || preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $key, $m) !== 1) {
            return $current;
        }

        $period = $this->forMonth((int) $m[1], (int) $m[2]);

        return ! $allowFuture && $period->key() > $current->key() ? $current : $period;
    }

    public function previous(Period $period): Period
    {
        $label = $this->labelMonth($period)->subMonthNoOverflow();

        return $this->forMonth($label->year, $label->month);
    }

    public function next(Period $period): Period
    {
        $label = $this->labelMonth($period)->addMonthNoOverflow();

        return $this->forMonth($label->year, $label->month);
    }

    /**
     * First day of the label month, e.g. 2026-11-01 — how budgets store their period.
     */
    public function labelMonth(Period $period): CarbonImmutable
    {
        return CarbonImmutable::create($period->year, $period->month, 1)->startOfDay();
    }
}
