<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Family;

use App\Enums\FamilyRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /invitations: invite an email with a role (BR-I02, BR-I03 in InvitationService).
 */
class InvitationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(FamilyRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'email', 'role' => 'peran'];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    public function role(): FamilyRole
    {
        return FamilyRole::from((string) $this->validated('role'));
    }
}
