<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Family;
use App\Models\Scopes\FamilyScope;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-owned model: scoped to the active family and stamped with its id on create.
 * See docs/03-multi-tenancy.md.
 *
 * @mixin Model
 */
trait BelongsToFamily
{
    public static function bootBelongsToFamily(): void
    {
        static::addGlobalScope(new FamilyScope);

        static::creating(function (Model $model): void {
            $model->setAttribute(
                'family_id',
                $model->getAttribute('family_id') ?? app(TenantContext::class)->id(),
            );
        });
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
