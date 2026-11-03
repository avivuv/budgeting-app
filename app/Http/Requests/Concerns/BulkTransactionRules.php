<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk action on selected transactions: delete or change category, shared by
 * the API and the legacy web request (docs/api-contracts/transactions.md).
 *
 * @mixin FormRequest
 */
trait BulkTransactionRules
{
    public const int MAX_ITEMS = 100;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['delete', 'category'])],
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'category_id' => ['nullable', 'integer', 'min:1', 'required_if:action,category'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => 'aksi',
            'ids' => 'transaksi terpilih',
            'category_id' => 'kategori',
        ];
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_values(array_map('intval', (array) $this->validated('ids')));
    }
}
