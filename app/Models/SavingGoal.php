<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use App\Models\Concerns\LogsFamilyActivity;
use Carbon\CarbonImmutable;
use Database\Factories\SavingGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A savings target (docs/11 §K).
 *
 * @property int $id
 * @property int $family_id
 * @property string $name
 * @property int $target_amount
 * @property int|null $wallet_id
 * @property int $saved_amount
 * @property CarbonImmutable|null $deadline
 * @property string|null $icon
 * @property string|null $color
 * @property Carbon|null $achieved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet|null $wallet
 */
#[Fillable(['name', 'target_amount', 'wallet_id', 'saved_amount', 'deadline', 'icon', 'color', 'achieved_at'])]
class SavingGoal extends Model
{
    /** @use HasFactory<SavingGoalFactory> */
    use BelongsToFamily, HasFactory, LogsFamilyActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'saved_amount' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'saved_amount' => 'integer',
            'deadline' => 'immutable_date:Y-m-d',
            'achieved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
