<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Wallet;

use App\DTOs\Wallet\WalletData;
use App\Enums\WalletType;
use App\Http\Requests\Concerns\ParsesAmounts;
use App\Support\Money\AmountParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalletRequest extends FormRequest
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $this->parseAmounts(['initial_balance']);

        if ($this->input('initial_balance') === null || $this->input('initial_balance') === '') {
            $this->merge(['initial_balance' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::enum(WalletType::class)],
            // Opening balance may be negative (e.g. an existing credit card bill).
            'initial_balance' => ['required', 'integer', 'min:-'.AmountParser::MAX, 'max:'.AmountParser::MAX],
            'color' => ['nullable', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama', 'type' => 'jenis', 'initial_balance' => 'saldo awal'];
    }

    public function toData(): WalletData
    {
        return WalletData::from($this->validated());
    }
}
