<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sensitive account actions ask for the current password again (instead of
 * the web's "confirm password" session step), so they work with tokens too.
 */
class ConfirmPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['password' => $this->currentPasswordRules()];
    }
}
