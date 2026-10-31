<?php

declare(strict_types=1);

namespace App\Support\Period;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Monthly periods that start on the family's "period_start_day" (BR-D01, BR-D02).
 *
 * start_day = 1  => "Nov 2026" = 1 Nov – 30 Nov 2026
 * start_day = 25 => "Nov 2026" = 25 Oct – 24 Nov 2026 (labelled by the month of its end date)
 */
final class PeriodResolver
{
    public static function forMonth(int $year, int $month, int $startDay = 1): Period
    {
        self::assertStartDay($startDay);

        $labelMonth = CarbonImmutable::create($year, $month, 1)->startOfDay();

        if ($startDay === 1) {
            return new Period($labelMonth, $labelMonth->endOfMonth()->startOfDay(), $year, $month);
        }

        $start = $labelMonth->subMonthNoOverflow()->setDay($startDay);
        $end = $labelMonth->setDay($startDay - 1);

        return new Period($start, $end, $year, $month);
    }

    /**
     * The period a given date belongs to.
     */
    public static function containing(CarbonImmutable $date, int $startDay = 1): Period
    {
        self::assertStartDay($startDay);

        $label = $date->startOfDay()->startOfMonth();

        if ($startDay > 1 && $date->day >= $startDay) {
            $label = $label->addMonthNoOverflow();
        }

        return self::forMonth($label->year, $label->month, $startDay);
    }

    private static function assertStartDay(int $startDay): void
    {
        if ($startDay < 1 || $startDay > 28) {
            throw new InvalidArgumentException('period_start_day must be between 1 and 28.');
        }
    }
}
