<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ReportRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Same range rules as the web reports. Also used by /summary (period key).
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
            'period' => ['nullable', 'date_format:Y-m'],
            'wallet_id' => ['nullable', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
