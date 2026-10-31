<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class for business-rule violations. Rendered as a flash error (web),
 * a JSON message (API) or a chat reply (Telegram) — see bootstrap/app.php.
 */
abstract class DomainException extends RuntimeException
{
    /** Message shown to the user, in Bahasa Indonesia. */
    abstract public function userMessage(): string;

    public function status(): int
    {
        return 422;
    }

    /** Form field the error belongs to, if any. */
    public function field(): ?string
    {
        return null;
    }
}
