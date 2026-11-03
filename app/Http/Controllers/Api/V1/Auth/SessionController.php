<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\TwoFactorChallengeRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Cookie-session login for the web SPA (docs/api-contracts/auth.md).
 * Two-factor users finish signing in at /auth/two-factor-challenge.
 */
class SessionController extends Controller
{
    use RespondsWithMe;

    private const string PENDING_USER = 'login.id';

    private const string PENDING_REMEMBER = 'login.remember';

    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function store(LoginRequest $request): JsonResponse
    {
        if ($current = $this->currentUser()) {
            return $this->me($current)->response();
        }

        $user = $this->auth->verifyCredentials($request->string('email')->value(), $request->string('password')->value());
        $remember = $request->boolean('remember');

        if ($this->auth->requiresTwoFactor($user)) {
            $request->session()->put([self::PENDING_USER => $user->id, self::PENDING_REMEMBER => $remember]);

            return response()->json(['message' => __('auth.two_factor_required'), 'two_factor' => true], 202);
        }

        return $this->startSession($request, $user, $remember);
    }

    public function twoFactor(TwoFactorChallengeRequest $request): JsonResponse
    {
        $user = $this->auth->pendingTwoFactorUser($request->session()->get(self::PENDING_USER));

        $this->auth->verifyTwoFactor($user, $request->input('code'), $request->input('recovery_code'));

        $remember = (bool) $request->session()->pull(self::PENDING_REMEMBER, false);
        $request->session()->forget(self::PENDING_USER);

        return $this->startSession($request, $user, $remember);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    private function startSession(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        return $this->me($user)->response();
    }

    private function currentUser(): ?User
    {
        $user = Auth::guard('web')->user();

        return $user instanceof User ? $user : null;
    }
}
