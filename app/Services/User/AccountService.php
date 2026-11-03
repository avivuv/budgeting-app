<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Enums\FamilyRole;
use App\Exceptions\Auth\AuthException;
use App\Exceptions\Family\MembershipRuleException;
use App\Models\Family;
use App\Models\User;
use App\Repositories\Contracts\FamilyRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Actions\DeletePasskey;
use Laravel\Passkeys\Passkey;

/**
 * The signed-in user's own account (docs/api-contracts/settings.md):
 * profile, password, account deletion, two-factor and passkeys.
 */
class AccountService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly FamilyRepositoryInterface $families,
        private readonly EnableTwoFactorAuthentication $enableTwoFactor,
        private readonly ConfirmTwoFactorAuthentication $confirmTwoFactor,
        private readonly DisableTwoFactorAuthentication $disableTwoFactor,
        private readonly GenerateNewRecoveryCodes $newRecoveryCodes,
        private readonly DeletePasskey $deletePasskey,
    ) {}

    /**
     * A changed email must be verified again; the verification link is sent.
     *
     * @param  array{name?: string, email?: string}  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $emailChanged = isset($attributes['email']) && mb_strtolower($attributes['email']) !== mb_strtolower($user->email);
        $user = $this->users->updateProfile($user, $attributes);

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    public function changePassword(User $user, string $password): void
    {
        $this->users->updatePassword($user, $password);
    }

    /**
     * BR-I01: an owner whose family still has other members must hand the
     * family over first. Transactions stay as "Mantan anggota" (BR-I04, BR-I08).
     */
    public function deleteAccount(User $user): void
    {
        $this->ensureDeletable($user);

        $this->users->deleteAccount($user);
    }

    /**
     * Check BR-I01 before signing the user out; deleteAccount() checks again.
     */
    public function ensureDeletable(User $user): void
    {
        foreach ($this->families->forUser($user) as $family) {
            /** @var Family $family */
            if ($this->families->roleOf($family, $user) === FamilyRole::Owner && $this->families->members($family)->count() > 1) {
                throw MembershipRuleException::ownerCannotLeave();
            }
        }
    }

    /**
     * Start two-factor setup: a new secret and recovery codes, not active
     * until confirmed with a code from the authenticator app.
     *
     * @return array{qr_code_svg: string, setup_key: string, recovery_codes: list<string>}
     */
    public function startTwoFactor(User $user): array
    {
        if ($user->hasEnabledTwoFactorAuthentication()) {
            throw AuthException::twoFactorAlreadyEnabled();
        }

        // A setup that was never confirmed gets a fresh secret.
        ($this->enableTwoFactor)($user, force: true);

        return $this->twoFactorSetup($user->refresh());
    }

    public function confirmTwoFactor(User $user, string $code): void
    {
        try {
            ($this->confirmTwoFactor)($user, $code);
        } catch (ValidationException) {
            throw AuthException::invalidTwoFactorCode();
        }
    }

    public function disableTwoFactor(User $user): void
    {
        ($this->disableTwoFactor)($user);
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            throw AuthException::twoFactorNotEnabled();
        }

        ($this->newRecoveryCodes)($user);

        return array_values($user->refresh()->recoveryCodes());
    }

    /**
     * @return Collection<int, Passkey>
     */
    public function passkeys(User $user): Collection
    {
        return $this->users->passkeys($user);
    }

    public function deletePasskey(User $user, int $id): void
    {
        $passkey = $this->users->findPasskey($user, $id)
            ?? throw (new ModelNotFoundException)->setModel(Passkey::class, [$id]);

        ($this->deletePasskey)($user, $passkey);
    }

    /**
     * @return array{qr_code_svg: string, setup_key: string, recovery_codes: list<string>}
     */
    private function twoFactorSetup(User $user): array
    {
        return [
            'qr_code_svg' => $user->twoFactorQrCodeSvg(),
            'setup_key' => Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret),
            'recovery_codes' => array_values($user->recoveryCodes()),
        ];
    }
}
