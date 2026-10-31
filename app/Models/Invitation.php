<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FamilyRole;
use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitation to join a family (BR-I02). Valid 7 days, single use, only for the invited email.
 *
 * @property int $id
 * @property int $family_id
 * @property string $email
 * @property FamilyRole $role
 * @property string $token
 * @property int|null $invited_by
 * @property Carbon|null $accepted_at
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'role', 'token', 'invited_by', 'accepted_at', 'expires_at'])]
#[Hidden(['token'])]
class Invitation extends Model
{
    use BelongsToFamily;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => FamilyRole::class,
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
