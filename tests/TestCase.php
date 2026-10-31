<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Wrap both the main and the activity log database in a transaction so
     * RefreshDatabase isolates tests on the two connections.
     *
     * @var array<int, string>
     */
    protected array $connectionsToTransact = ['mysql', 'activity'];

    protected function setUp(): void
    {
        parent::setUp();

        // HTTP tests must not depend on a built frontend (public/build manifest).
        $this->withoutVite();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
