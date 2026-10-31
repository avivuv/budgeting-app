<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Exceptions\Tenancy\TenantNotResolvedException;
use App\Models\Family;

/**
 * The family (tenant) of the current request, job or command.
 * Registered as a scoped singleton (reset between requests and queued jobs).
 */
class TenantContext
{
    private ?Family $family = null;

    public function set(Family $family): void
    {
        $this->family = $family;
    }

    public function get(): Family
    {
        return $this->family ?? throw new TenantNotResolvedException;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    public function has(): bool
    {
        return $this->family !== null;
    }

    public function forget(): void
    {
        $this->family = null;
    }

    /**
     * Run a callback inside another family's context, restoring the previous one.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(Family $family, callable $callback): mixed
    {
        $previous = $this->family;
        $this->family = $family;

        try {
            return $callback();
        } finally {
            $this->family = $previous;
        }
    }
}
