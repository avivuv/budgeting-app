<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Receipt\Parsers\FakeReceiptParser;
use App\Services\Receipt\Parsers\GeminiReceiptParser;
use App\Services\Receipt\Parsers\ReceiptParserInterface;
use App\Services\Tenancy\TenantContext;
use App\Support\Inertia\ModulePageFinder;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Receipt AI provider from RECEIPT_PARSER (docs/06 §4).
        $this->app->singleton(ReceiptParserInterface::class, fn ($app): ReceiptParserInterface => match ($app['config']->get('receipt.driver')) {
            'gemini' => new GeminiReceiptParser(
                apiKey: (string) $app['config']->get('receipt.gemini.api_key'),
                model: (string) $app['config']->get('receipt.model'),
                fallbackModel: $app['config']->get('receipt.fallback_model') ?: null,
                fallbackBelow: (float) $app['config']->get('receipt.fallback_below_confidence'),
                endpoint: (string) $app['config']->get('receipt.gemini.endpoint'),
                timeout: (int) $app['config']->get('receipt.timeout'),
            ),
            default => new FakeReceiptParser,
        });

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

        // Owner/admin of the active family: wallets, categories, members (docs/03 §4).
        Gate::define('manage-family', function (User $user): bool {
            $tenant = app(TenantContext::class);

            return $tenant->has()
                && (app(FamilyService::class)->roleOf($tenant->get(), $user)?->canManage() ?? false);
        });

        // REST API: 60 requests per minute per token/user (docs/13-rest-api.md).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by((string) ($request->user()?->currentAccessToken()?->getKey() ?? $request->user()?->getAuthIdentifier() ?? $request->ip())));

        // ProcessReceiptJob: stay under the AI provider's free-tier rate (docs/06 §5.1).
        RateLimiter::for('receipts', fn () => Limit::perMinute(max(1, (int) config('receipt.rate_per_minute'))));
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
