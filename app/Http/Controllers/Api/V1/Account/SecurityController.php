<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\ConfirmPasswordRequest;
use App\Http\Requests\Api\V1\Account\TwoFactorCodeRequest;
use App\Models\User;
use App\Services\User\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Passkeys\Passkey;

/**
 * Two-factor authentication and passkeys of the signed-in user
 * (docs/api-contracts/settings.md). Passkeys are registered from the
 * browser through the passkeys package endpoints (WebAuthn needs the session).
 */
class SecurityController extends Controller
{
    public function __construct(private readonly AccountService $account) {}

    public function enableTwoFactor(ConfirmPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->account->startTwoFactor($user)]);
    }

    public function confirmTwoFactor(TwoFactorCodeRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->account->confirmTwoFactor($user, $request->code());

        return response()->json(['message' => 'Autentikasi dua faktor aktif.', 'data' => ['two_factor_enabled' => true]]);
    }

    public function disableTwoFactor(ConfirmPasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->account->disableTwoFactor($user);

        return response()->noContent();
    }

    public function recoveryCodes(ConfirmPasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->account->regenerateRecoveryCodes($user)]);
    }

    public function passkeys(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->account->passkeys($user)->map(fn (Passkey $passkey): array => [
            'id' => $passkey->id,
            'name' => $passkey->name,
            'authenticator' => $passkey->authenticator,
            'created_at' => $passkey->created_at?->toIso8601String(),
            'last_used_at' => $passkey->last_used_at?->toIso8601String(),
        ])->values()]);
    }

    public function destroyPasskey(ConfirmPasswordRequest $request, int $id): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->account->deletePasskey($user, $id);

        return response()->noContent();
    }
}
