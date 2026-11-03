<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use App\Http\Requests\Concerns\BulkTransactionRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk delete or category change (docs/api-contracts/transactions.md).
 */
class BulkTransactionRequest extends FormRequest
{
    use BulkTransactionRules;
}
