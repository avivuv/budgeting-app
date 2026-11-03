<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\EnsureSessionClient;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentFamily;
use App\Http\Responses\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The web SPA logs in with the session cookie on /api (Sanctum stateful domains).
        $middleware->statefulApi();

        $middleware->alias([
            'session.client' => EnsureSessionClient::class,
            'family' => SetCurrentFamily::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // The tenant must be known before route model binding queries family-scoped models.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetCurrentFamily::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // REST API: Indonesian error bodies, business rules carry their rule id.
        $exceptions->render(fn (Throwable $e, Request $request) => (new ApiExceptionRenderer)->render($e, $request));

        // Business-rule violations on the (legacy Inertia) web: JSON for fetch calls, form error + toast otherwise.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->userMessage(),
                    'errors' => [$e->field() ?? 'general' => [$e->userMessage()]],
                ], $e->status());
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $e->userMessage()]);

            return back()->withInput()->withErrors([$e->field() ?? 'general' => $e->userMessage()]);
        });
    })->create();
