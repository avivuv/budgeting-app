<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Records create/update/delete of fillable attributes (old → new) in the activity
 * log database, tagged with the record's family (BR-B14).
 *
 * @mixin Model
 */
trait LogsFamilyActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->getTable());
    }

    public function beforeActivityLogged(Model $activity, string $event): void
    {
        $activity->setAttribute('family_id', $this->getAttribute('family_id'));
    }
}
