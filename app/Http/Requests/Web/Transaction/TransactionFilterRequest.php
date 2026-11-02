<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Transaction;

use App\Http\Requests\Concerns\TransactionFilterRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Transaction list filter; same rules for web and API.
 */
class TransactionFilterRequest extends FormRequest
{
    use TransactionFilterRules;
}
