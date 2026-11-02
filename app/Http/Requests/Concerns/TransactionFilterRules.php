<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DTOs\Transaction\TransactionFilterData;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Support\Money\AmountParser;
use Illuminate\Validation\Rule;

/**
 * Query-string filter for transaction lists, identical for the web page and
 * the REST API (docs/13-rest-api.md, docs/15 §6b). Sorts are whitelisted.
 * Ids of another family simply match nothing (BR-I06).
 */
trait TransactionFilterRules
{
    use ParsesAmounts;

    protected function prepareForValidation(): void
    {
        $this->parseAmounts(['min', 'max']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'category_id' => ['nullable', 'array', 'max:50'],
            'category_id.*' => ['integer', 'min:1'],
            'wallet_id' => ['nullable', 'array', 'max:50'],
            'wallet_id.*' => ['integer', 'min:1'],
            'user_id' => ['nullable', 'array', 'max:50'],
            'user_id.*' => ['integer', 'min:1'],
            'tag' => ['nullable', 'array', 'max:20'],
            'tag.*' => ['string', 'max:50'],
            'source' => ['nullable', Rule::enum(TransactionSource::class)],
            'min' => ['nullable', 'integer', 'min:0', 'max:'.AmountParser::MAX],
            'max' => ['nullable', 'integer', 'min:0', 'max:'.AmountParser::MAX],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(TransactionFilterData::SORTS)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from' => 'tanggal awal',
            'to' => 'tanggal akhir',
            'type' => 'jenis',
            'category_id' => 'kategori',
            'wallet_id' => 'dompet',
            'user_id' => 'anggota',
            'source' => 'sumber',
            'min' => 'nominal minimum',
            'max' => 'nominal maksimum',
            'q' => 'pencarian',
            'sort' => 'urutan',
            'per_page' => 'jumlah per halaman',
        ];
    }

    public function toFilter(): TransactionFilterData
    {
        $ints = fn (string $key): array => array_values(array_map('intval', (array) $this->validated($key, [])));
        $type = $this->validated('type');
        $source = $this->validated('source');

        return new TransactionFilterData(
            from: $this->validated('from'),
            to: $this->validated('to'),
            type: $type !== null ? TransactionType::from($type) : null,
            categoryIds: $ints('category_id'),
            walletIds: $ints('wallet_id'),
            userIds: $ints('user_id'),
            tags: array_values(array_map(fn (string $tag): string => mb_strtolower(ltrim(trim($tag), '#')), (array) $this->validated('tag', []))),
            source: $source !== null ? TransactionSource::from($source) : null,
            min: $this->validated('min') !== null ? (int) $this->validated('min') : null,
            max: $this->validated('max') !== null ? (int) $this->validated('max') : null,
            search: $this->validated('q'),
            sort: $this->validated('sort') ?? '-date',
        );
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? 20);
    }

    /**
     * The validated filter as it appears in the query string (for the filter UI).
     *
     * @return array<string, mixed>
     */
    public function filterQuery(): array
    {
        return array_filter(
            collect($this->validated())->except('per_page')->all(),
            fn ($value): bool => $value !== null && $value !== [] && $value !== '',
        );
    }
}
