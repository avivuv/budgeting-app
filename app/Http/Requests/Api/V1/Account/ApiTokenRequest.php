<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /me/tokens: a named token, read-only unless can_write.
 */
class ApiTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'can_write' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama token'];
    }

    public function tokenName(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function canWrite(): bool
    {
        return (bool) $this->validated('can_write', false);
    }
}
