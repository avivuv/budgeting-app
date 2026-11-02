<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Report;

use App\Enums\ReportTab;
use App\Http\Requests\Concerns\ReportRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'tab' => ['nullable', Rule::enum(ReportTab::class)],
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
        ];
    }

    public function tab(): ReportTab
    {
        return ReportTab::tryFrom((string) $this->validated('tab')) ?? ReportTab::Cashflow;
    }
}
