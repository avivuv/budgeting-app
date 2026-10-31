<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query to the active family. Throws when no family is set
 * (fail closed) instead of silently returning all families' data.
 *
 * @implements Scope<Model>
 */
class FamilyScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('family_id'), app(TenantContext::class)->id());
    }
}
