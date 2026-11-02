<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Dashboard;

use App\DTOs\Transaction\TransactionFilterData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Global dashboard filter: period (YYYY-MM), wallet and member (docs/07 §3).
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
            'period' => ['nullable', 'date_format:Y-m'],
            'wallet' => ['nullable', 'integer', 'min:1'],
            'member' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function period(): ?string
    {
        return $this->validated('period');
    }

    public function scope(): TransactionFilterData
    {
        return new TransactionFilterData(
            walletIds: $this->filled('wallet') ? [(int) $this->validated('wallet')] : [],
            userIds: $this->filled('member') ? [(int) $this->validated('member')] : [],
        );
    }

    /**
     * @return array{period: string|null, wallet: int|null, member: int|null}
     */
    public function filters(): array
    {
        return [
            'period' => $this->period(),
            'wallet' => $this->filled('wallet') ? (int) $this->validated('wallet') : null,
            'member' => $this->filled('member') ? (int) $this->validated('member') : null,
        ];
    }
}
