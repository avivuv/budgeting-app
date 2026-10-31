<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Read access to the activity log database (budgeting_app_log). Activity rows
 * are not family-scoped by a global scope (they live in another database), so
 * every query filters on family_id explicitly.
 */
interface ActivityRepositoryInterface
{
    /**
     * Newest first.
     *
     * @return Collection<int, Activity>
     */
    public function forSubject(int $familyId, Model $subject, int $limit = 50): Collection;
}
