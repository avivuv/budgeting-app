<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Family;
use App\Models\User;
use App\Services\User\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The signed-in user with their families (docs/api-contracts/auth.md, object `Me`).
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @param  Collection<int, Family>  $families  families of the user, with the membership pivot
     */
    public function __construct(User $user, private readonly Collection $families, private readonly ?int $currentFamilyId)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->resource->hasEnabledTwoFactorAuthentication(),
            'appearance' => $this->appearance->value,
            /** @var int|null */
            'current_family_id' => $this->currentFamilyId,
            'families' => $this->families->map(fn (Family $family): array => [
                'id' => $family->id,
                'name' => $family->name,
                'role' => $family->membership?->role->value,
                'role_label' => $family->membership?->role->label(),
                'can' => [
                    'write' => $family->membership?->role->canWrite() ?? false,
                    'manage' => $family->membership?->role->canManage() ?? false,
                ],
            ])->values()->all(),
            /** @var int */
            'unread_notifications' => app(NotificationService::class)->unreadCount($this->resource),
        ];
    }
}
