<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use App\Models\Concerns\LogsFamilyActivity;
use Carbon\CarbonImmutable;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Spending limit of an expense category for one monthly period (BR-F01).
 *
 * @property int $id
 * @property int $family_id
 * @property int $category_id
 * @property CarbonImmutable $period first day of the label month
 * @property int $amount
 * @property Carbon|null $alert_80_sent_at
 * @property Carbon|null $alert_100_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 */
#[Fillable(['category_id', 'period', 'amount', 'alert_80_sent_at', 'alert_100_sent_at'])]
class Budget extends Model
{
    /** @use HasFactory<BudgetFactory> */
    use BelongsToFamily, HasFactory, LogsFamilyActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'immutable_date:Y-m-d',
            'amount' => 'integer',
            'alert_80_sent_at' => 'datetime',
            'alert_100_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
