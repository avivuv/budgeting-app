<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Savings;

use App\Http\Requests\Concerns\ParsesAmounts;
use App\Support\Money\AmountParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add to ("deposit") or take out of ("withdraw") a goal (BR-K02).
 */
class ContributeRequest extends FormRequest
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $this->parseAmounts(['amount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['deposit', 'withdraw'])],
            'amount' => ['required', 'integer', 'min:1', 'max:'.AmountParser::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => 'nominal', 'direction' => 'jenis'];
    }

    public function signedAmount(): int
    {
        $amount = (int) $this->validated('amount');

        return $this->validated('direction') === 'withdraw' ? -$amount : $amount;
    }
}
