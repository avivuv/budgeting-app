<?php

declare(strict_types=1);

namespace App\Exceptions\Receipt;

use App\Exceptions\DomainException;

/**
 * The AI provider could not read the file (network, quota, invalid output).
 * ProcessReceiptJob retries; a permanent failure marks the receipt failed and
 * gives the scan back (BR-H05).
 */
final class ReceiptParserException extends DomainException
{
    public function __construct(
        string $message,
        public readonly bool $rateLimited = false,
    ) {
        parent::__construct($message);
    }

    public static function rateLimited(string $provider): self
    {
        return new self("{$provider} rate limit reached", rateLimited: true);
    }

    public function userMessage(): string
    {
        return $this->rateLimited
            ? 'Struk sedang antre, sebentar lagi dibaca.'
            : 'Struk tidak terbaca. Ketik manual, misalnya: belanja 87500.';
    }

    public function status(): int
    {
        return 503;
    }
}
