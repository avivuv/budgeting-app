<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Archive (true) or restore (false) a wallet (docs/api-contracts/wallets.md).
 */
class ArchiveWalletRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['archived' => ['required', 'boolean']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['archived' => 'arsip'];
    }
}
