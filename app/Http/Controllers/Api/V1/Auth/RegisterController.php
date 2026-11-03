<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sign-up for the web SPA: creates the account, signs in, sends the verification email.
 * Validation lives in the Fortify CreateNewUser action (same rules as before).
 */
class RegisterController extends Controller
{
    use RespondsWithMe;

    public function __construct(
        private readonly AuthService $auth,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $current = Auth::guard('web')->user();

        if ($current instanceof User) {
            return $this->me($current)->response();
        }

        /** @var array<string, string> $input */
        $input = $request->only(['name', 'email', 'password', 'password_confirmation']);
        $user = $this->auth->register($input);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return $this->me($user)->response()->setStatusCode(201);
    }
}
