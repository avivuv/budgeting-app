<?php

declare(strict_types=1);

namespace App\DTOs\Dashboard;

use App\Support\Period\Period;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A monthly period as shown in the period picker (BR-D01, BR-D02).
 */
#[TypeScript]
class PeriodData extends Data
{
    public function __construct(
        public string $key,
        public string $label,
        public string $range,
        public string $start,
        public string $end,
        public string $previousKey,
        public ?string $nextKey,
        public bool $isCurrent,
    ) {}

    public static function fromPeriod(Period $period, Period $current): self
    {
        $label = CarbonImmutable::create($period->year, $period->month, 1);
        $previous = $label->subMonthNoOverflow();
        $next = $label->addMonthNoOverflow();
        $isCurrent = $period->key() === $current->key();

        return new self(
            key: $period->key(),
            label: $label->translatedFormat('F Y'),
            range: $period->start->translatedFormat('j M').' – '.$period->end->translatedFormat('j M Y'),
            start: $period->start->toDateString(),
            end: $period->end->toDateString(),
            previousKey: $previous->format('Y-m'),
            nextKey: $isCurrent ? null : $next->format('Y-m'),
            isCurrent: $isCurrent,
        );
    }
}
