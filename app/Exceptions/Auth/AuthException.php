<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use App\Exceptions\DomainException;

/**
 * Failed sign-in steps (docs/api-contracts/auth.md). Rendered as 422 with the field.
 */
final class AuthException extends DomainException
{
    private function __construct(
        private readonly string $userMessageText,
        private readonly string $fieldName,
    ) {
        parent::__construct($userMessageText);
    }

    /**
     * Same message for an unknown email and a wrong password, so emails cannot be guessed.
     */
    public static function invalidCredentials(): self
    {
        return new self(__('auth.failed'), 'email');
    }

    public static function invalidTwoFactorCode(string $field = 'code'): self
    {
        return new self(__('auth.two_factor_invalid'), $field);
    }

    public static function twoFactorExpired(): self
    {
        return new self(__('auth.two_factor_expired'), 'general');
    }

    public function userMessage(): string
    {
        return $this->userMessageText;
    }

    public function field(): string
    {
        return $this->fieldName;
    }
}
