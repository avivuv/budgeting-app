<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\Appearance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppearanceUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'appearance' => ['required', Rule::enum(Appearance::class)],
        ];
    }

    public function appearance(): Appearance
    {
        return $this->enum('appearance', Appearance::class) ?? Appearance::System;
    }
}
