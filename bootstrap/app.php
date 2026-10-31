<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentFamily;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;

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

        $middleware->alias([
            'family' => SetCurrentFamily::class,
        ]);

        // The tenant must be known before route model binding queries family-scoped models.
        $middleware->prependToPriorityList(SubstituteBindings::class, SetCurrentFamily::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Business-rule violations: JSON for the API, form error + toast for the web.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->userMessage(),
                    'errors' => [$e->field() ?? 'general' => [$e->userMessage()]],
                ], $e->status());
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $e->userMessage()]);

            return back()->withInput()->withErrors([$e->field() ?? 'general' => $e->userMessage()]);
        });
    })->create();
