<?php

namespace App\Providers;

use App\Services\Tenancy\TenantContext;
use App\Support\Inertia\ModulePageFinder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant per request / queued job; reset automatically between them.
        $this->app->scoped(TenantContext::class);

        // Inertia pages live in resources/js/modules/<feature>/pages (see app.ts).
        $this->app->bind('inertia.view-finder', fn ($app) => new ModulePageFinder(
            $app['files'],
            $app['config']->get('inertia.pages.paths'),
            $app['config']->get('inertia.pages.extensions'),
        ));

        // Telescope is a dev-only dependency (not auto-discovered).
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
