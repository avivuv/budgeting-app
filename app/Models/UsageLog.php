<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly usage counter of a metered feature (e.g. receipt scans), the basis
 * of the family's quota (docs/06 §6).
 *
 * @property int $id
 * @property int $family_id
 * @property string $feature
 * @property CarbonImmutable $period
 * @property int $count
 */
#[Fillable(['feature', 'period', 'count'])]
class UsageLog extends Model
{
    use BelongsToFamily;

    public const string RECEIPT_SCAN = 'receipt_scan';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'immutable_date:Y-m-d',
            'count' => 'integer',
        ];
    }
}
