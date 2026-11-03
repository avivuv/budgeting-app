<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\TokenRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\User\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Bearer tokens for the Android app and other non-browser clients
 * (docs/api-contracts/auth.md).
 */
class TokenController extends Controller
{
    use RespondsWithMe;

    public function __construct(
        private readonly AuthService $auth,
        private readonly ApiTokenService $tokens,
    ) {}

    public function store(TokenRequest $request): JsonResponse
    {
        $token = $this->auth->issueToken(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->string('device_name')->value(),
            $request->input('code'),
            $request->input('recovery_code'),
        );

        if ($token === null) {
            return response()->json(['message' => __('auth.two_factor_required'), 'two_factor' => true], 202);
        }

        /** @var User $user */
        $user = $token->accessToken->tokenable;

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $token->accessToken->abilities,
                'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
                'user' => $this->me($user)->resolve($request),
            ],
        ], 201);
    }

    /**
     * Revoke the token used for this request; a session (SPA) has none, so nothing happens.
     */
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->bearerToken() !== null) {
            $this->tokens->revoke($user, (int) $user->currentAccessToken()->getKey());
        }

        return response()->noContent();
    }
}
