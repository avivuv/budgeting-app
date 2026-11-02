<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Free text for TransactionParser, the same for web quick entry and the API.
 */
trait ParseTransactionRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['text' => 'teks'];
    }

    public function text(): string
    {
        return (string) $this->validated('text');
    }
}
