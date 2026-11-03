<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session-based auth endpoints (login, logout, 2FA, passkeys, register) only
 * work for the web SPA, whose requests Sanctum marks as stateful. Other
 * clients are pointed to POST /api/v1/auth/token (docs/api-contracts/auth.md).
 */
class EnsureSessionClient
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return response()->json(['message' => __('auth.session_only')], 400);
        }

        return $next($request);
    }
}
