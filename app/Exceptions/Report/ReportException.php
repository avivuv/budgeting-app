<?php

declare(strict_types=1);

namespace App\Exceptions\Report;

use App\Exceptions\DomainException;

/**
 * A dashboard/report request that cannot be answered (docs/api-contracts/reports.md).
 */
final class ReportException extends DomainException
{
    private function __construct(
        private readonly string $userMessageText,
        private readonly string $fieldName,
    ) {
        parent::__construct($userMessageText);
    }

    public static function periodNotStarted(): self
    {
        return new self('Periode tersebut belum dimulai.', 'period');
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
