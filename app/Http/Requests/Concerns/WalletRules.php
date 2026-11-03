<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Wallet\WalletData;
use App\Enums\WalletType;
use App\Support\Money\AmountParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wallet form, shared by the API and the legacy web request so both validate
 * identically (docs/api-contracts/wallets.md).
 *
 * @mixin FormRequest
 */
trait WalletRules
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        // Opening balance may be negative, e.g. "-2jt" for a credit card bill (BR-C06).
        $this->parseSignedAmounts(['initial_balance']);

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
        return ['name' => 'nama', 'type' => 'jenis', 'initial_balance' => 'saldo awal', 'color' => 'warna', 'icon' => 'ikon'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $invalid = 'Saldo awal tidak valid.';

        return [
            'initial_balance.integer' => $invalid,
            'initial_balance.min' => $invalid,
            'initial_balance.max' => $invalid,
        ];
    }

    public function toData(): WalletData
    {
        return WalletData::from($this->validated());
    }
}
