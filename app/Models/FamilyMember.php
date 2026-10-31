<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FamilyRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot family_user: a user's membership and role in a family.
 *
 * @property int $family_id
 * @property int $user_id
 * @property FamilyRole $role
 * @property Carbon $joined_at
 */
class FamilyMember extends Pivot
{
    protected $table = 'family_user';

    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => FamilyRole::class,
            'joined_at' => 'datetime',
        ];
    }
}
