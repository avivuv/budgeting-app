<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Account;

use App\Concerns\ProfileValidationRules;
use App\Enums\Appearance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /me: any of name, email and appearance (same rules as registration).
 */
class ProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', ...$this->nameRules()],
            'email' => ['sometimes', ...$this->emailRules($this->user()?->getAuthIdentifier())],
            'appearance' => ['sometimes', Rule::enum(Appearance::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'nama', 'email' => 'email', 'appearance' => 'tampilan'];
    }

    /**
     * @return array{name?: string, email?: string}
     */
    public function profile(): array
    {
        /** @var array{name?: string, email?: string} $profile */
        $profile = array_map(fn (mixed $value): string => trim((string) $value), $this->safe()->only(['name', 'email']));

        if (isset($profile['email'])) {
            $profile['email'] = mb_strtolower($profile['email']);
        }

        return $profile;
    }

    public function appearance(): ?Appearance
    {
        return $this->has('appearance') ? $this->enum('appearance', Appearance::class) : null;
    }
}
