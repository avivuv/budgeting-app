<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Personal access token for a device (Android). See docs/api-contracts/auth.md.
 */
class TokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ];
    }
}
