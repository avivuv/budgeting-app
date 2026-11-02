<?php

declare(strict_types=1);

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel export of a report (docs/07 §5.1). Holds prepared sheets only; every
 * number comes from ReportService through ExportService.
 */
class ReportWorkbook implements Export, WithMultipleSheets
{
    /**
     * @param  list<ArraySheet>  $sheets
     */
    public function __construct(
        private readonly array $sheets,
    ) {}

    /**
     * @return list<ArraySheet>
     */
    public function sheets(): array
    {
        return $this->sheets;
    }
}
