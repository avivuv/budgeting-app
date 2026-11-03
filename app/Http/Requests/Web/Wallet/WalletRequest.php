<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Wallet;

use App\Http\Requests\Concerns\WalletRules;
use Illuminate\Foundation\Http\FormRequest;

class WalletRequest extends FormRequest
{
    use WalletRules;
}
