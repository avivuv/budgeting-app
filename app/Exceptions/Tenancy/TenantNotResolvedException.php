<?php

declare(strict_types=1);

namespace App\Exceptions\Tenancy;

use LogicException;

/**
 * Thrown when tenant-scoped data is touched without an active family.
 * This is a programming error (fail closed), not a user-facing message.
 */
class TenantNotResolvedException extends LogicException
{
    public function __construct()
    {
        parent::__construct('No family is set on the TenantContext; tenant-scoped queries are blocked.');
    }
}
