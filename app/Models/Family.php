<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * The tenant. Not itself family-scoped.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $currency
 * @property string $timezone
 * @property int $period_start_day
 * @property int $receipt_quota_monthly
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $suspended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FamilyMember|null $membership
 */
#[Fillable(['name', 'slug', 'currency', 'timezone', 'period_start_day', 'settings'])]
class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'IDR',
        'timezone' => 'Asia/Jakarta',
        'period_start_day' => 1,
        'receipt_quota_monthly' => 50,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start_day' => 'integer',
            'receipt_quota_monthly' => 'integer',
            'settings' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<User, $this, FamilyMember, 'membership'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(FamilyMember::class)
            ->as('membership')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
