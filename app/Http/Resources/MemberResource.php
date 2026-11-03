<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A family member (docs/api-contracts/family.md), loaded through Family::members().
 *
 * @mixin User
 */
class MemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->membership?->role->value,
            'role_label' => $this->membership?->role->label(),
            'joined_at' => $this->membership?->joined_at->toIso8601String(),
            'is_self' => $request->user()?->getAuthIdentifier() === $this->id,
        ];
    }
}
