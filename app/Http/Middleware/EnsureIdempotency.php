<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache as CacheFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-B13: a POST repeated with the same Idempotency-Key (per family + user)
 * within 24 hours returns the first response instead of creating data again.
 * Requests without the header are processed normally.
 *
 * Routes that work without (or change) the active family use the "user"
 * scope, so creating a family does not change the key of its retry.
 */
class EnsureIdempotency
{
    public const int TTL_SECONDS = 86_400;

    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     * @param  'family'|'user'  $scope
     */
    public function handle(Request $request, Closure $next, string $scope = 'family'): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $request->isMethod('POST') || $key === null || $key === '') {
            return $next($request);
        }

        if (strlen($key) > 100) {
            return response()->json(['message' => 'Idempotency-Key maksimal 100 karakter.'], 422);
        }

        $cacheKey = sprintf(
            'idempotency:%d:%d:%s:%s',
            $scope === 'family' && $this->tenant->has() ? $this->tenant->id() : 0,
            $request->user()?->getAuthIdentifier() ?? 0,
            $request->path(),
            hash('sha256', $key),
        );

        $cache = CacheFacade::store();

        if (($stored = $this->stored($cache, $cacheKey)) !== null) {
            return $stored;
        }

        // Concurrent duplicates: only one request may do the work.
        $lock = CacheFacade::lock($cacheKey.':lock', 30);

        if (! $lock->get()) {
            return response()->json(['message' => 'Permintaan yang sama sedang diproses.'], 409);
        }

        try {
            if (($stored = $this->stored($cache, $cacheKey)) !== null) {
                return $stored;
            }

            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                $cache->put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                    'type' => $response->headers->get('Content-Type'),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Re-read after acquiring the lock: a concurrent twin may have stored a result meanwhile.
     *
     * @phpstan-impure
     */
    private function stored(Cache $cache, string $cacheKey): ?Response
    {
        $stored = $cache->get($cacheKey);

        if (! is_array($stored)) {
            return null;
        }

        return response((string) $stored['content'], (int) $stored['status'])
            ->header('Content-Type', (string) ($stored['type'] ?? 'application/json'))
            ->header('Idempotent-Replayed', 'true');
    }
}
