<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /me/two-factor/confirm: the 6-digit code from the authenticator app.
 */
class TwoFactorCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'string', 'digits:6']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode'];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
