<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the signed-in user's active family into the TenantContext.
 * Users without a family are sent to onboarding.
 */
class SetCurrentFamily
{
    public function __construct(
        private readonly FamilyService $families,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $family = $this->families->resolveActive($user);

        if ($family === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Buat atau gabung ke keluarga terlebih dahulu.'], 409)
                : redirect()->route('onboarding.create');
        }

        // BR-I05: suspended families cannot read or write.
        abort_if($family->isSuspended() && ! $user->is_super_admin, 403, 'Keluarga ini sedang ditangguhkan.');

        $this->tenant->set($family);

        return $next($request);
    }
}
