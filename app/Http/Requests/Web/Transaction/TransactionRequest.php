<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Transaction;

use App\DTOs\Transaction\TransactionData;
use App\Enums\TransactionSource;
use App\Http\Requests\Concerns\TransactionRules;
use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    use TransactionRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->transactionRules();
    }

    public function toData(): TransactionData
    {
        return TransactionData::from([...$this->validated(), 'source' => TransactionSource::Web]);
    }
}
