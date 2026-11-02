<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Receipt;

use App\Http\Requests\Concerns\ReceiptRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Corrections to a receipt draft (BR-H09).
 */
class ReceiptDraftRequest extends FormRequest
{
    use ReceiptRules;

    protected function prepareForValidation(): void
    {
        $this->prepareDraft();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->draftRules();
    }
}
