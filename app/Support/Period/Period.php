<?php

declare(strict_types=1);

namespace App\Support\Period;

use Carbon\CarbonImmutable;

/**
 * A monthly budgeting period. Both ends are inclusive dates (BR-D04).
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $year,
        public int $month,
    ) {}

    /**
     * Label key, e.g. "2026-11".
     */
    public function key(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function contains(CarbonImmutable $date): bool
    {
        $day = $date->startOfDay();

        return $day->greaterThanOrEqualTo($this->start) && $day->lessThanOrEqualTo($this->end);
    }

    /**
     * Days left in the period counting $today (BR-F06). 0 when the period is over.
     */
    public function daysRemaining(CarbonImmutable $today): int
    {
        $today = $today->startOfDay();

        if ($today->greaterThan($this->end)) {
            return 0;
        }

        $from = $today->lessThan($this->start) ? $this->start : $today;

        return (int) $from->diffInDays($this->end) + 1;
    }
}
