<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Transaction;

use App\DTOs\Transaction\TransactionData;
use App\Enums\TransactionSource;
use App\Http\Requests\Concerns\TransactionRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Same rules as the web form (BR-B12); only the recorded source differs.
 */
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
        return TransactionData::from([...$this->validated(), 'source' => $this->source()]);
    }

    /**
     * BR-B07: the web SPA is signed in through the session cookie, other clients with a token.
     */
    public function source(): TransactionSource
    {
        return $this->hasSession() && Auth::guard('web')->check() ? TransactionSource::Web : TransactionSource::Api;
    }
}
