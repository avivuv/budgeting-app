<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A violated business rule from docs/11-business-rules.md, with the user-facing
 * message and the form field it belongs to.
 */
class BusinessRuleException extends DomainException
{
    public function __construct(
        public readonly string $rule,
        private readonly string $userMessageText,
        private readonly ?string $fieldName = null,
    ) {
        parent::__construct("[{$rule}] {$userMessageText}");
    }

    public function userMessage(): string
    {
        return $this->userMessageText;
    }

    public function field(): ?string
    {
        return $this->fieldName;
    }
}
