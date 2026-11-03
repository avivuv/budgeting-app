<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Forgotten password and email verification links (docs/api-contracts/auth.md).
 */
class PasswordController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /**
     * Always the same answer, registered email or not, so emails cannot be guessed.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendPasswordResetLink($request->string('email')->value());

        return response()->json(['message' => __('passwords.sent')]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        /** @var array{token: string, email: string, password: string, password_confirmation: string} $input */
        $input = $request->only(['token', 'email', 'password', 'password_confirmation']);
        $this->auth->resetPassword($input);

        return response()->json(['message' => __('passwords.reset')]);
    }

    public function verificationNotification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->auth->sendVerificationEmail($user)
            ? response()->json(['message' => __('auth.verification_sent')], 202)
            : response()->json(['message' => __('auth.already_verified')]);
    }
}
