<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/*
| Scenarios: docs/test-cases/auth.md · Contract: docs/api-contracts/auth.md
|
| The web SPA logs in with the session cookie, which Sanctum only starts for
| requests from a stateful domain — hence the Referer header in spa().
*/

beforeEach(function () {
    config(['sanctum.stateful' => ['spa.test']]);
    Notification::fake();
});

/**
 * Requests as the web SPA would send them.
 */
function spa(): TestCase
{
    return test()->withHeader('Referer', 'http://spa.test/login');
}

function ayah(array $attributes = []): User
{
    return User::factory()->create(['name' => 'Ayah', 'email' => 'ayah@budgeting.test', ...$attributes]);
}

/**
 * Turns on 2FA with a real secret; returns the secret to compute codes.
 */
function enableTwoFactor(User $user, array $recoveryCodes = ['AAAA-BBBB']): string
{
    $secret = app(Google2FA::class)->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes)),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $secret;
}

function otp(string $secret): string
{
    return app(Google2FA::class)->getCurrentOtp($secret);
}

describe('positive', function () {
    it('[AUTH-01] logs in on the web with a differently cased email and stays logged in', function () {
        // GIVEN Ayah has an account and a family
        $ayah = ayah();
        $family = createFamily($ayah, 'Keluarga Ayah');

        // WHEN he logs in typing his email in capitals
        $response = spa()->postJson('/api/v1/auth/login', ['email' => 'AYAH@budgeting.test', 'password' => 'password']);

        // THEN he gets his profile, and the next request knows who he is
        $response->assertOk()
            ->assertJsonPath('data.id', $ayah->id)
            ->assertJsonPath('data.name', 'Ayah')
            ->assertJsonPath('data.current_family_id', $family->id)
            ->assertJsonPath('data.families.0.role', 'owner');
        $this->assertAuthenticatedAs($ayah, 'web');

        spa()->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'ayah@budgeting.test');
    });

    it('[AUTH-02] asks two-factor users for a code before signing them in', function () {
        $ayah = ayah();
        $secret = enableTwoFactor($ayah);

        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])
            ->assertStatus(202)
            ->assertJsonPath('two_factor', true)
            ->assertJsonPath('message', 'Masukkan kode autentikasi dua faktor.');
        $this->assertGuest('web');

        spa()->postJson('/api/v1/auth/two-factor-challenge', ['code' => otp($secret)])
            ->assertOk()
            ->assertJsonPath('data.id', $ayah->id)
            ->assertJsonPath('data.two_factor_enabled', true);
        $this->assertAuthenticatedAs($ayah, 'web');
    });

    it('[AUTH-03] accepts a recovery code once and replaces it', function () {
        $ayah = ayah();
        enableTwoFactor($ayah, ['AAAA-BBBB', 'CCCC-DDDD']);

        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])->assertStatus(202);
        spa()->postJson('/api/v1/auth/two-factor-challenge', ['recovery_code' => 'AAAA-BBBB'])->assertOk();

        expect($ayah->fresh()->recoveryCodes())
            ->not->toContain('AAAA-BBBB')
            ->toContain('CCCC-DDDD')
            ->toHaveCount(2);
    });

    it('[AUTH-04] logs out and the session no longer works', function () {
        ayah();
        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])->assertOk();

        spa()->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertGuest('web');
        app('auth')->forgetGuards();
        spa()->getJson('/api/v1/me')->assertUnauthorized();
    });

    it('[AUTH-05] registers a new account, signs in and sends the verification email', function () {
        $response = spa()->postJson('/api/v1/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@budgeting.test',
            'password' => 'rahasia-budi-123',
            'password_confirmation' => 'rahasia-budi-123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Budi')
            ->assertJsonPath('data.email', 'budi@budgeting.test')
            ->assertJsonPath('data.email_verified', false)
            ->assertJsonPath('data.current_family_id', null)
            ->assertJsonPath('data.families', []);

        $budi = User::where('email', 'budi@budgeting.test')->sole();
        expect(Hash::check('rahasia-budi-123', $budi->password))->toBeTrue()
            ->and($budi->password)->not->toBe('rahasia-budi-123');
        $this->assertAuthenticatedAs($budi, 'web');
        Notification::assertSentTo($budi, VerifyEmail::class);
    });

    it('[AUTH-06] resets a forgotten password through the emailed link', function () {
        $ayah = ayah();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ayah@budgeting.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar, link reset password sudah dikirim.');

        $token = null;
        Notification::assertSentTo($ayah, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'ayah@budgeting.test',
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ])->assertOk()->assertJsonPath('message', 'Password berhasil diganti. Silakan login.');

        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])->assertUnprocessable();
        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password-baru-456'])->assertOk();
    });

    it('[AUTH-07] sends the verification email again on request', function () {
        $budi = User::factory()->unverified()->create();

        spa()->actingAs($budi, 'web')
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(202)
            ->assertJsonPath('message', 'Link verifikasi baru sudah dikirim ke email kamu.');

        Notification::assertSentTo($budi, VerifyEmail::class);
    });

    it('[AUTH-08] gives the Android app a token that reads the profile', function () {
        $this->travelTo('2026-11-03 10:00:00');
        $ayah = ayah();

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'ayah@budgeting.test',
            'password' => 'password',
            'device_name' => 'Pixel 7 — Ayah',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.abilities', ['read', 'write'])
            ->assertJsonPath('data.expires_at', now()->addDays(90)->toIso8601String())
            ->assertJsonPath('data.user.id', $ayah->id);

        $stored = PersonalAccessToken::sole();
        expect($stored->name)->toBe('Pixel 7 — Ayah')
            ->and($stored->tokenable_id)->toBe($ayah->id);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ayah@budgeting.test');
    });

    it('[AUTH-09] needs the two-factor code before handing out a token', function () {
        $ayah = ayah();
        $secret = enableTwoFactor($ayah);
        $input = ['email' => 'ayah@budgeting.test', 'password' => 'password', 'device_name' => 'Pixel 7'];

        $this->postJson('/api/v1/auth/token', $input)
            ->assertStatus(202)
            ->assertJsonPath('two_factor', true);
        expect(PersonalAccessToken::count())->toBe(0);

        $this->postJson('/api/v1/auth/token', [...$input, 'code' => otp($secret)])->assertCreated();
        expect(PersonalAccessToken::count())->toBe(1);
    });

    it('[AUTH-10] revokes only the token used to log out', function () {
        $ayah = ayah();
        $phone = $ayah->createToken('Pixel 7', ['read', 'write'])->plainTextToken;
        $tablet = $ayah->createToken('Tablet', ['read', 'write'])->plainTextToken;

        $this->withToken($phone)->deleteJson('/api/v1/auth/token')->assertNoContent();

        app('auth')->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
        app('auth')->forgetGuards();
        $this->withToken($tablet)->getJson('/api/v1/me')->assertOk();
        expect(PersonalAccessToken::pluck('name')->all())->toBe(['Tablet']);
    });

    it('[AUTH-12] hands the web the WebAuthn options for a passkey login', function () {
        spa()->getJson('/api/v1/auth/passkeys/options')
            ->assertOk()
            ->assertJsonStructure(['options' => ['challenge']]);
    });

    it('[AUTH-11] shows the profile of a user without a family instead of 409', function () {
        $budi = User::factory()->create();

        $this->withToken($budi->createToken('Pixel', ['read'])->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.current_family_id', null)
            ->assertJsonPath('data.families', []);
    });
});

describe('negative', function () {
    it('[AUTH-21] rejects a wrong password without starting a session', function () {
        ayah();

        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'salah'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email atau password salah.');

        $this->assertGuest('web');
    });

    it('[AUTH-22] answers an unknown email exactly like a wrong password', function () {
        spa()->postJson('/api/v1/auth/login', ['email' => 'tidakada@budgeting.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Email atau password salah.',
                'errors' => ['email' => ['Email atau password salah.']],
            ]);
    });

    it('[AUTH-23] blocks the sixth login attempt within a minute', function () {
        ayah();

        foreach (range(1, 5) as $attempt) {
            spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'salah'])->assertUnprocessable();
        }

        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', fn (string $message) => str_starts_with($message, 'Terlalu banyak permintaan. Coba lagi dalam'));
        $this->assertGuest('web');
    });

    it('[AUTH-24] rejects a wrong two-factor code', function () {
        $ayah = ayah();
        enableTwoFactor($ayah);
        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])->assertStatus(202);

        spa()->postJson('/api/v1/auth/two-factor-challenge', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode autentikasi tidak valid.');

        $this->assertGuest('web');
    });

    it('[AUTH-25] rejects a two-factor code when no login is waiting for it', function () {
        spa()->postJson('/api/v1/auth/two-factor-challenge', ['code' => '123456'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.general.0', 'Sesi login sudah kedaluwarsa. Silakan login ulang.');
    });

    it('[AUTH-26] refuses to register a taken email or a mismatched confirmation', function () {
        ayah();

        spa()->postJson('/api/v1/auth/register', [
            'name' => 'Ayah Kedua',
            'email' => 'ayah@budgeting.test',
            'password' => 'rahasia-123',
            'password_confirmation' => 'rahasia-beda',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email sudah terdaftar.')
            ->assertJsonPath('errors.password.0', 'Konfirmasi password tidak cocok.');

        expect(User::count())->toBe(1);
        $this->assertGuest('web');
    });

    it('[AUTH-27] does not reveal whether an email is registered', function () {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'tidakada@budgeting.test'])
            ->assertOk()
            ->assertJsonPath('message', 'Jika email terdaftar, link reset password sudah dikirim.');

        Notification::assertNothingSent();
    });

    it('[AUTH-28] rejects a reset with a wrong token and keeps the old password', function () {
        $ayah = ayah();

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'token-palsu',
            'email' => 'ayah@budgeting.test',
            'password' => 'password-baru-456',
            'password_confirmation' => 'password-baru-456',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Link reset password tidak valid atau sudah kedaluwarsa.');

        expect(Hash::check('password', $ayah->fresh()->password))->toBeTrue();
    });

    it('[AUTH-29] gives no token for a wrong password or a missing device name', function () {
        ayah();

        $this->postJson('/api/v1/auth/token', ['email' => 'ayah@budgeting.test', 'password' => 'salah', 'device_name' => 'Pixel'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Email atau password salah.');

        $this->postJson('/api/v1/auth/token', ['email' => 'ayah@budgeting.test', 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.device_name.0', 'Nama perangkat wajib diisi.');

        expect(PersonalAccessToken::count())->toBe(0);
    });

    it('[AUTH-30] gives no token for a wrong two-factor code', function () {
        $ayah = ayah();
        enableTwoFactor($ayah);

        $this->postJson('/api/v1/auth/token', [
            'email' => 'ayah@budgeting.test', 'password' => 'password', 'device_name' => 'Pixel', 'code' => '000000',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Kode autentikasi tidak valid.');

        expect(PersonalAccessToken::count())->toBe(0);
    });

    it('[AUTH-31] rejects an expired token', function () {
        $this->travelTo('2026-11-03 10:00:00');
        ayah();
        $token = $this->postJson('/api/v1/auth/token', [
            'email' => 'ayah@budgeting.test', 'password' => 'password', 'device_name' => 'Pixel',
        ])->json('data.token');

        $this->travel(91)->days();

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Silakan login terlebih dahulu.']);
    });

    it('[AUTH-32] points non-browser clients to the token endpoint', function () {
        ayah();

        $this->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'password'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Endpoint ini hanya untuk web. Aplikasi lain memakai POST /api/v1/auth/token.');

        $this->assertGuest('web');
    });

    it('[AUTH-33] does not create a second account or session for a signed-in user', function () {
        $ayah = ayah();

        spa()->actingAs($ayah, 'web')
            ->postJson('/api/v1/auth/register', [
                'name' => 'Lain', 'email' => 'lain@budgeting.test', 'password' => 'rahasia-123', 'password_confirmation' => 'rahasia-123',
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $ayah->id);

        spa()->postJson('/api/v1/auth/login', ['email' => 'ayah@budgeting.test', 'password' => 'salah'])
            ->assertOk()
            ->assertJsonPath('data.id', $ayah->id);

        expect(User::count())->toBe(1);
    });
});
