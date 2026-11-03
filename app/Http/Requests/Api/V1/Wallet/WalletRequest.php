<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Http\Requests\Concerns\WalletRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or update a wallet (docs/api-contracts/wallets.md).
 */
class WalletRequest extends FormRequest
{
    use WalletRules;
}
