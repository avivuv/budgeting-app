<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /me/password: the current password and the new one, confirmed.
 */
class PasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['current_password' => 'password saat ini', 'password' => 'password baru'];
    }
}
