<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\DTOs\Transaction\TransactionFilterData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /summary and /dashboard/{widget}: period plus wallet/member filter.
 * Unknown ids simply match nothing — data stays tenant-scoped (BR-I06).
 */
class DashboardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'string', 'date_format:Y-m'],
            'wallet_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['period.date_format' => 'Format periode tidak valid.'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['wallet_id' => 'dompet', 'user_id' => 'anggota', 'period' => 'periode'];
    }

    public function period(): ?string
    {
        $period = $this->validated('period');

        return is_string($period) ? $period : null;
    }

    public function scope(): TransactionFilterData
    {
        return new TransactionFilterData(
            walletIds: $this->filled('wallet_id') ? [(int) $this->validated('wallet_id')] : [],
            userIds: $this->filled('user_id') ? [(int) $this->validated('user_id')] : [],
        );
    }
}
