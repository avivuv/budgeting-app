<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Report;

use App\Enums\TransactionType;
use App\Http\Requests\Concerns\ReportRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /reports/{report} and its export: same range rules as the web reports.
 */
class ReportRequest extends FormRequest
{
    use ReportRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->reportRules(),
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
        ];
    }

    public function year(): ?int
    {
        return $this->validated('year') !== null ? (int) $this->validated('year') : null;
    }

    public function type(): TransactionType
    {
        return TransactionType::tryFrom((string) $this->validated('type')) ?? TransactionType::Expense;
    }

    public function exportFormat(): string
    {
        return $this->validated('format') === 'pdf' ? 'pdf' : 'xlsx';
    }
}
