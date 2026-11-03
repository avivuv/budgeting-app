<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use App\Enums\FamilyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /members/{id}: the new role (BR-I01, BR-I03 in MembershipService).
 */
class MemberRoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(FamilyRole::class)]];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => 'peran'];
    }

    public function role(): FamilyRole
    {
        return FamilyRole::from((string) $this->validated('role'));
    }
}
