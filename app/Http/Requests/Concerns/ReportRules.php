<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;

/**
 * Report range for the web page, the exports and the REST API: a free date
 * range (inclusive, BR-D04, at most one year) or a year for the yearly report.
 * Without dates the caller uses the current period.
 */
trait ReportRules
{
    public const int MAX_RANGE_DAYS = 366;

    /**
     * @return array<string, mixed>
     */
    protected function reportRules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $from = $this->input('from');
                    if (is_string($from) && is_string($value)
                        && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($value)) >= self::MAX_RANGE_DAYS) {
                        $fail('Rentang laporan maksimal satu tahun.');
                    }
                },
            ],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'type' => ['nullable', Rule::in(['income', 'expense'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['from' => 'tanggal awal', 'to' => 'tanggal akhir', 'year' => 'tahun', 'type' => 'jenis'];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function range(): ?array
    {
        $from = $this->validated('from');
        $to = $this->validated('to');

        return is_string($from) && is_string($to)
            ? [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->startOfDay()]
            : null;
    }
}
