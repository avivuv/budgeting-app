<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Account\ConfirmPasswordRequest;
use App\Http\Requests\Api\V1\Account\PasswordRequest;
use App\Http\Requests\Api\V1\Account\ProfileRequest;
use App\Models\User;
use App\Services\User\AccountService;
use App\Services\User\AppearanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * The signed-in user's profile, password and account (docs/api-contracts/settings.md).
 */
class ProfileController extends Controller
{
    use RespondsWithMe;

    public function __construct(
        private readonly AccountService $account,
        private readonly AppearanceService $appearance,
    ) {}

    public function update(ProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->profile() !== []) {
            $user = $this->account->updateProfile($user, $request->profile());
        }

        if (($appearance = $request->appearance()) !== null) {
            $user = $this->appearance->update($user, $appearance);
        }

        return $this->me($user)->response();
    }

    public function password(PasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->account->changePassword($user, (string) $request->validated('password'));

        return response()->json(['message' => 'Password diperbarui.']);
    }

    /**
     * BR-I01: blocked while the user owns a family with other members.
     */
    public function destroy(ConfirmPasswordRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->account->ensureDeletable($user);

        // Sign the SPA session out first: logging out saves the remember
        // token, which would write a deleted user back.
        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $this->account->deleteAccount($user);

        return response()->noContent();
    }
}
