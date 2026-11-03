<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\ApiTokenRequest;
use App\Models\User;
use App\Services\User\ApiTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal API tokens of the signed-in user (docs/api-contracts/settings.md).
 * The plain-text token is returned once, on creation.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        // The SPA session carries no personal token (only a transient one).
        $currentId = $request->bearerToken() !== null ? $user->currentAccessToken()->id : null;

        return response()->json(['data' => $this->tokens->list($user)
            ->map(fn (PersonalAccessToken $token): array => $this->token($token, $currentId))
            ->values()]);
    }

    public function store(ApiTokenRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $new = $this->tokens->create($user, $request->tokenName(), $request->canWrite());

        return response()->json([
            'message' => 'Token dibuat. Salin sekarang, token tidak akan ditampilkan lagi.',
            'data' => [...$this->token($new->accessToken, null), 'plain_text_token' => $new->plainTextToken],
        ], 201);
    }

    public function destroy(Request $request, int $token): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->tokens->revoke($user, $token), 404);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function token(PersonalAccessToken $token, ?int $currentId): array
    {
        return [
            'id' => $token->id,
            'name' => $token->name,
            'can_write' => in_array(ApiTokenService::WRITE, $token->abilities ?? [], true),
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'is_current' => $token->id === $currentId,
        ];
    }
}
