<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\Concerns\ParseTransactionRules;
use Illuminate\Foundation\Http\FormRequest;

class ParseTransactionRequest extends FormRequest
{
    use ParseTransactionRules;
}
