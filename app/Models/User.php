<?php

namespace App\Models;

use App\Enums\Appearance;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Appearance $appearance
 * @property int|null $current_family_id
 * @property bool $is_super_admin
 * @property-read FamilyMember|null $membership pivot when loaded through Family::members()
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'appearance'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Mirrors the database defaults so new, unsaved/unrefreshed models are complete.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'appearance' => 'system',
        'is_super_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'appearance' => Appearance::class,
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Family, $this, FamilyMember, 'membership'>
     */
    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class)
            ->using(FamilyMember::class)
            ->as('membership')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function currentFamily(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'current_family_id');
    }
}
