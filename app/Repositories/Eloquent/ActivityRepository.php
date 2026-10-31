<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Activity;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ActivityRepository implements ActivityRepositoryInterface
{
    public function forSubject(int $familyId, Model $subject, int $limit = 50): Collection
    {
        return Activity::query()
            ->where('family_id', $familyId)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
