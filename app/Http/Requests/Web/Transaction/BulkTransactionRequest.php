<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Transaction;

use App\Http\Requests\Concerns\BulkTransactionRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk action on selected transactions: delete or change category (docs/07 §4).
 */
class BulkTransactionRequest extends FormRequest
{
    use BulkTransactionRules;
}
