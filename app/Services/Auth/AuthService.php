<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Exceptions\Auth\AuthException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\User\ApiTokenService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\NewAccessToken;

/**
 * Sign-in for every client (docs/api-contracts/auth.md). Starting the web
 * session is the controller's job; this class only checks who the user is.
 * Account creation and password reset reuse the Fortify actions.
 */
class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly ApiTokenService $tokens,
        private readonly TwoFactorAuthenticationProvider $twoFactor,
        private readonly CreateNewUser $createUser,
        private readonly ResetUserPassword $resetPassword,
    ) {}

    public function verifyCredentials(string $email, string $password): User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw AuthException::invalidCredentials();
        }

        return $user;
    }

    public function requiresTwoFactor(User $user): bool
    {
        return $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * The user who passed the password step and still owes a two-factor code.
     */
    public function pendingTwoFactorUser(mixed $userId): User
    {
        $user = is_int($userId) ? $this->users->find($userId) : null;

        if (! $user instanceof User) {
            throw AuthException::twoFactorExpired();
        }

        return $user;
    }

    /**
     * Check a code from the authenticator app, or use up a recovery code.
     */
    public function verifyTwoFactor(User $user, ?string $code, ?string $recoveryCode): void
    {
        if ($recoveryCode !== null && $recoveryCode !== '') {
            $valid = collect($user->recoveryCodes())->first(fn (string $stored): bool => hash_equals($stored, $recoveryCode));

            if ($valid === null) {
                throw AuthException::invalidTwoFactorCode('recovery_code');
            }

            $user->replaceRecoveryCode($valid);

            return;
        }

        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        if ($code === null || $code === '' || ! $this->twoFactor->verify($secret, $code)) {
            throw AuthException::invalidTwoFactorCode();
        }
    }

    /**
     * Personal access token for a device (Android). Two-factor users must pass a code;
     * null means the code is still missing and the client should ask for it.
     */
    public function issueToken(string $email, string $password, string $deviceName, ?string $code, ?string $recoveryCode): ?NewAccessToken
    {
        $user = $this->verifyCredentials($email, $password);

        if ($this->requiresTwoFactor($user)) {
            if (($code === null || $code === '') && ($recoveryCode === null || $recoveryCode === '')) {
                return null;
            }

            $this->verifyTwoFactor($user, $code, $recoveryCode);
        }

        return $this->tokens->create($user, $deviceName, canWrite: true);
    }

    /**
     * @param  array<string, string>  $input
     */
    public function register(array $input): User
    {
        $user = $this->createUser->create($input);

        event(new Registered($user));

        return $user;
    }

    /**
     * Sends the reset link when the email exists. The caller answers the same
     * either way so emails cannot be guessed.
     */
    public function sendPasswordResetLink(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }

    /**
     * @param  array{token: string, email: string, password: string, password_confirmation: string}  $input
     */
    public function resetPassword(array $input): void
    {
        $status = Password::broker()->reset($input, function (User $user) use ($input): void {
            $this->resetPassword->reset($user, $input);
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
    }

    /**
     * True when a new link was sent, false when the email was already verified.
     */
    public function sendVerificationEmail(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->sendEmailVerificationNotification();

        return true;
    }
}
