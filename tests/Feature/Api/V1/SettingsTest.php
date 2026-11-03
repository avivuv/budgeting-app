<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Passkeys\Passkey;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/settings.md · Contract: docs/api-contracts/settings.md
|
| Golden family: Ayah owner, Ibu member; every password is "password".
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    Notification::fake();
    $this->golden = GoldenFamily::seed();
    $this->ibu = $this->golden->ibu;
});

function accountAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

/** Ask for a token the way the Android app logs in; returns the HTTP status. */
function tokenLogin(string $email, string $password, ?string $code = null): int
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/token', array_filter([
        'email' => $email, 'password' => $password, 'device_name' => 'Pixel', 'code' => $code,
    ]))->status();
}

function authenticatorCode(string $secret, int $step = 0): string
{
    $google2fa = app(Google2FA::class);

    return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + $step);
}

function addPasskey(User $user, string $name): Passkey
{
    return Passkey::query()->forceCreate([
        'user_id' => $user->id,
        'name' => $name,
        'credential_id' => 'cred-'.$name.'-'.$user->id,
        'credential' => [],
    ]);
}

describe('positive', function () {
    it('[SET-01] changes name, email and appearance; a new email must be verified again', function () {
        // GIVEN Ibu with a verified email
        accountAs($this->ibu);

        // WHEN she changes her profile
        $response = $this->patchJson('/api/v1/me', ['name' => 'Ibu Ani', 'email' => 'Ani@Contoh.com', 'appearance' => 'dark']);

        // THEN /me reads the new values and a verification link goes to the new address
        $response->assertOk()
            ->assertJsonPath('data.name', 'Ibu Ani')
            ->assertJsonPath('data.email', 'ani@contoh.com')
            ->assertJsonPath('data.appearance', 'dark')
            ->assertJsonPath('data.email_verified', false);

        $this->getJson('/api/v1/me')->assertJsonPath('data.name', 'Ibu Ani')->assertJsonPath('data.email', 'ani@contoh.com');
        Notification::assertSentTo($this->ibu->refresh(), VerifyEmail::class);
    });

    it('[SET-02] changes the password; only the new one logs in', function () {
        accountAs($this->ibu);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
        ])->assertOk()->assertJsonPath('message', 'Password diperbarui.');

        expect(tokenLogin($this->ibu->email, 'rahasia-baru-123'))->toBe(201)
            ->and(tokenLogin($this->ibu->email, 'password'))->toBe(422);
    });

    it('[SET-03] sets up two-factor with the QR code and a code from the app; login then asks for a code', function () {
        accountAs($this->ibu);

        $setup = $this->postJson('/api/v1/me/two-factor', ['password' => 'password'])
            ->assertOk()
            ->assertJsonCount(8, 'data.recovery_codes')
            ->json('data');
        expect($setup['qr_code_svg'])->toStartWith('<svg')
            ->and($setup['setup_key'])->not->toBeEmpty();
        $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', false);

        $this->postJson('/api/v1/me/two-factor/confirm', ['code' => authenticatorCode($setup['setup_key'])])
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        accountAs($this->ibu->refresh());
        $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', true);
        // A one-time code cannot be used twice, so log in with the next one.
        expect(tokenLogin($this->ibu->email, 'password'))->toBe(202)
            ->and(tokenLogin($this->ibu->email, 'password', authenticatorCode($setup['setup_key'])))->toBe(422)
            ->and(tokenLogin($this->ibu->email, 'password', authenticatorCode($setup['setup_key'], step: 1)))->toBe(201);
    });

    it('[SET-04] replaces the recovery codes and turns two-factor off', function () {
        accountAs($this->ibu);
        $setup = $this->postJson('/api/v1/me/two-factor', ['password' => 'password'])->json('data');
        $this->postJson('/api/v1/me/two-factor/confirm', ['code' => authenticatorCode($setup['setup_key'])])->assertOk();

        $codes = $this->postJson('/api/v1/me/two-factor/recovery-codes', ['password' => 'password'])->assertOk()->json('data');
        expect($codes)->toHaveCount(8)->and(array_intersect($codes, $setup['recovery_codes']))->toBe([]);

        $this->deleteJson('/api/v1/me/two-factor', ['password' => 'password'])->assertNoContent();
        $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', false);
        expect(tokenLogin($this->ibu->email, 'password'))->toBe(201);
    });

    it('[SET-05] lists and deletes a passkey', function () {
        addPasskey($this->ibu, 'Laptop');
        accountAs($this->ibu);

        $id = $this->getJson('/api/v1/me/passkeys')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Laptop')
            ->json('data.0.id');

        $this->deleteJson("/api/v1/me/passkeys/{$id}", ['password' => 'password'])->assertNoContent();
        $this->getJson('/api/v1/me/passkeys')->assertJsonPath('data', []);
    });

    it('[SET-06] creates a read-only token, uses it, and revokes it', function () {
        accountAs($this->ibu);

        $created = $this->postJson('/api/v1/me/tokens', ['name' => 'Spreadsheet', 'can_write' => false])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Spreadsheet')
            ->assertJsonPath('data.can_write', false)
            ->json('data');
        expect($created['plain_text_token'])->toContain('|');

        // The token reads but cannot write.
        $this->app['auth']->forgetGuards();
        $this->withToken($created['plain_text_token'])->getJson('/api/v1/wallets')->assertOk();
        $this->withToken($created['plain_text_token'])->postJson('/api/v1/transactions', [])->assertForbidden();
        $this->withToken($created['plain_text_token'])->getJson('/api/v1/me/tokens')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Spreadsheet', 'is_current' => true]);

        // Revoked: unusable at once.
        $this->app['auth']->forgetGuards();
        accountAs($this->ibu);
        $this->deleteJson("/api/v1/me/tokens/{$created['id']}")->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($created['plain_text_token'])->getJson('/api/v1/wallets')->assertUnauthorized();
    });

    it('[SET-07] deletes a member account; her transactions stay as "Mantan anggota" [BR-I04]', function () {
        accountAs($this->ibu);

        $this->deleteJson('/api/v1/me', ['password' => 'password'])->assertNoContent();

        expect(User::query()->find($this->ibu->id))->toBeNull()
            ->and(tokenLogin($this->ibu->email, 'password'))->toBe(422);

        accountAs($this->golden->ayah);
        $orphans = Transaction::query()->whereNull('created_by')->count();
        expect($orphans)->toBe(3);
        $members = collect($this->getJson('/api/v1/dashboard/members')->json('data'));
        expect($members->firstWhere('name', 'Mantan anggota')['amount'])->toBe(GoldenFamily::EXPECTED['by_member']['Ibu']);
    });
});

describe('negative', function () {
    it('[SET-21] rejects a taken or invalid email and an empty name; the profile stays', function (array $input, string $field) {
        accountAs($this->ibu);
        $before = $this->ibu->only(['name', 'email']);

        $this->patchJson('/api/v1/me', $input)->assertUnprocessable()->assertJsonValidationErrors($field);

        expect($this->ibu->refresh()->only(['name', 'email']))->toBe($before);
    })->with([
        'taken email' => [fn () => ['email' => test()->golden->ayah->email], 'email'],
        'bad email' => [['email' => 'bukan-email'], 'email'],
        'empty name' => [['name' => ''], 'name'],
        'bad appearance' => [['appearance' => 'pink'], 'appearance'],
    ]);

    it('[SET-22] keeps the password when the current one is wrong or the new one is weak', function (array $input, string $field) {
        accountAs($this->ibu);

        $this->putJson('/api/v1/me/password', [...['current_password' => 'password', 'password' => 'rahasia-baru-123', 'password_confirmation' => 'rahasia-baru-123'], ...$input])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(tokenLogin($this->ibu->email, 'password'))->toBe(201);
    })->with([
        'wrong current' => [['current_password' => 'salah'], 'current_password'],
        'not confirmed' => [['password_confirmation' => 'lain-lagi-123'], 'password'],
        'too short' => [['password' => 'abc', 'password_confirmation' => 'abc'], 'password'],
    ]);

    it('[SET-23] asks for the right password before sensitive changes', function () {
        $passkey = addPasskey($this->ibu, 'Laptop');
        accountAs($this->ibu);

        $this->postJson('/api/v1/me/two-factor', ['password' => 'salah'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->deleteJson("/api/v1/me/passkeys/{$passkey->id}", ['password' => 'salah'])->assertUnprocessable();
        $this->deleteJson('/api/v1/me', [])->assertUnprocessable()->assertJsonValidationErrors('password');

        expect($this->ibu->refresh()->two_factor_secret)->toBeNull()
            ->and(Passkey::query()->count())->toBe(1)
            ->and(User::query()->find($this->ibu->id))->not->toBeNull();
    });

    it('[SET-24] refuses a wrong code and a second setup while two-factor is on', function () {
        accountAs($this->ibu);
        $setup = $this->postJson('/api/v1/me/two-factor', ['password' => 'password'])->json('data');

        $this->postJson('/api/v1/me/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode autentikasi tidak valid.');
        $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', false);
        $this->postJson('/api/v1/me/two-factor/recovery-codes', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Autentikasi dua faktor belum aktif.');

        $this->postJson('/api/v1/me/two-factor/confirm', ['code' => authenticatorCode($setup['setup_key'])])->assertOk();
        $this->postJson('/api/v1/me/two-factor', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Autentikasi dua faktor sudah aktif. Matikan dulu bila ingin mengatur ulang.');
        $this->getJson('/api/v1/me')->assertJsonPath('data.two_factor_enabled', true);
    });

    it('[SET-25] does not let an owner whose family has other members delete the account [BR-I01]', function () {
        accountAs($this->golden->ayah);

        $this->deleteJson('/api/v1/me', ['password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('rule', 'BR-I01');

        expect(User::query()->find($this->golden->ayah->id))->not->toBeNull();
    });

    it("[SET-26] cannot delete someone else's passkey or token", function () {
        $passkey = addPasskey($this->golden->ayah, 'HP Ayah');
        $token = $this->golden->ayah->createToken('Ayah', ['read']);
        accountAs($this->ibu);

        $this->deleteJson("/api/v1/me/passkeys/{$passkey->id}", ['password' => 'password'])->assertNotFound();
        $this->deleteJson("/api/v1/me/tokens/{$token->accessToken->id}")->assertNotFound();

        expect(Passkey::query()->count())->toBe(1)
            ->and(PersonalAccessToken::query()->whereKey($token->accessToken->id)->exists())->toBeTrue();
    });

    it('[SET-27] needs the write ability and a signed-in user', function () {
        accountAs($this->ibu, ['read']);
        $this->patchJson('/api/v1/me', ['name' => 'Ganti'])->assertForbidden();
        $this->postJson('/api/v1/me/tokens', ['name' => 'Baru'])->assertForbidden();
        $this->getJson('/api/v1/me/tokens')->assertOk();
        expect($this->ibu->refresh()->name)->toBe('Ibu');

        $this->app['auth']->forgetGuards();
        $this->patchJson('/api/v1/me', ['name' => 'Ganti'])->assertUnauthorized();
    });
});
