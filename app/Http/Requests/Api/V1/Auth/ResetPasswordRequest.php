<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Set a new password from the emailed link. See docs/api-contracts/auth.md.
 */
class ResetPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => $this->passwordRules(),
        ];
    }
}
