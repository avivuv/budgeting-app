<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\ReceiptRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Receipt or proof of payment upload (BR-H11).
 */
class UploadReceiptRequest extends FormRequest
{
    use ReceiptRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->uploadRules();
    }
}
