<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ReceiptRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "total" or "split" when saving a receipt (BR-H01, BR-H02).
 */
class ConfirmReceiptRequest extends FormRequest
{
    use ReceiptRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->confirmRules();
    }
}
