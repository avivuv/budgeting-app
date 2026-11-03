<?php

use App\Enums\FamilyRole;
use App\Models\Invitation;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\FamilyInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\GoldenFamily;

/*
| Scenarios: docs/test-cases/family.md · Contract: docs/api-contracts/family.md
|
| Golden family: Ayah is owner, Ibu is member. Today 31 Oct 2026.
*/

beforeEach(function () {
    $this->travelTo('2026-10-31 12:00:00');
    Notification::fake();
    $this->golden = GoldenFamily::seed();
});

function familyAs(User $user, array $abilities = ['read', 'write']): void
{
    Sanctum::actingAs($user, $abilities);
}

/** The stored invitation for an email, read outside any family scope. */
function invitationFor(string $email): ?Invitation
{
    return Invitation::query()->withoutGlobalScopes()->where('email', $email)->whereNull('accepted_at')->first();
}

/**
 * @return array<string, string> member name => role
 */
function memberRoles(): array
{
    return collect(test()->getJson('/api/v1/members')->assertOk()->json('data'))->pluck('role', 'name')->all();
}

describe('positive', function () {
    it('[FAM-01] lists the members to a member, without managing rights', function () {
        familyAs($this->golden->ibu, ['read']);

        $this->getJson('/api/v1/members')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Ayah', 'role' => 'owner', 'role_label' => 'Pemilik', 'is_self' => false])
            ->assertJsonFragment(['name' => 'Ibu', 'role' => 'member', 'role_label' => 'Anggota', 'is_self' => true])
            ->assertJsonPath('meta.can_manage', false)
            ->assertJsonPath('meta.roles.0.value', 'admin');
    });

    it('[FAM-02] invites "Adik@Contoh.com" as a viewer; the email is sent and the invitation listed [BR-I02]', function () {
        // GIVEN Ayah (owner)
        familyAs($this->golden->ayah);

        // WHEN he invites an email typed with capitals
        $response = $this->postJson('/api/v1/invitations', ['email' => 'Adik@Contoh.com', 'role' => 'viewer']);

        // THEN it is stored lower-case, valid 7 days, mailed and listed
        $response->assertCreated()
            ->assertJsonPath('message', 'Undangan dikirim ke adik@contoh.com.')
            ->assertJsonPath('data.email', 'adik@contoh.com')
            ->assertJsonPath('data.role', 'viewer')
            ->assertJsonPath('data.role_label', 'Hanya lihat')
            ->assertJsonPath('data.invited_by', 'Ayah')
            ->assertJsonPath('data.is_expired', false)
            ->assertJsonMissingPath('data.token');

        $invitation = invitationFor('adik@contoh.com');
        expect($invitation->family_id)->toBe($this->golden->family->id)
            ->and($invitation->expires_at->toDateString())->toBe('2026-11-07');
        Notification::assertSentOnDemand(FamilyInvitationNotification::class, fn ($notification, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === 'adik@contoh.com');

        $this->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'adik@contoh.com');
    });

    it('[FAM-03] lets the invited person look at the invitation and join [BR-I02] [BR-I07]', function () {
        familyAs($this->golden->ayah);
        $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'viewer'])->assertCreated();
        $token = invitationFor('adik@contoh.com')->token;

        // The invited person has no family yet.
        $adik = User::factory()->create(['name' => 'Adik', 'email' => 'adik@contoh.com']);
        familyAs($adik);

        $this->getJson("/api/v1/invitations/token/{$token}")
            ->assertOk()
            ->assertJsonPath('data.family_name', 'Keluarga Golden')
            ->assertJsonPath('data.role_label', 'Hanya lihat')
            ->assertJsonPath('data.can_accept', true)
            ->assertJsonPath('data.problem', null);

        $this->postJson("/api/v1/invitations/token/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('message', 'Selamat datang di Keluarga Golden!')
            ->assertJsonPath('data.current_family_id', $this->golden->family->id)
            ->assertJsonPath('data.families.0.role', 'viewer');

        $this->getJson('/api/v1/wallets')->assertOk()->assertJsonPath('meta.total_balance', GoldenFamily::EXPECTED['total_balance']);
        expect(memberRoles())->toMatchArray(['Adik' => 'viewer']);
    });

    it('[FAM-04] replaces a pending invitation when the same email is invited again', function () {
        familyAs($this->golden->ayah);
        $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'viewer'])->assertCreated();
        $oldToken = invitationFor('adik@contoh.com')->token;

        $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'member'])->assertCreated();

        $this->getJson('/api/v1/invitations')->assertJsonCount(1, 'data')->assertJsonPath('data.0.role', 'member');
        expect(invitationFor('adik@contoh.com')->token)->not->toBe($oldToken);
        $this->getJson("/api/v1/invitations/token/{$oldToken}")->assertNotFound();
    });

    it('[FAM-05] revokes an invitation; its link stops working', function () {
        familyAs($this->golden->ayah);
        $id = $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'viewer'])->json('data.id');
        $token = invitationFor('adik@contoh.com')->token;

        $this->deleteJson("/api/v1/invitations/{$id}")->assertNoContent();

        $this->getJson('/api/v1/invitations')->assertJsonPath('data', []);
        $this->getJson("/api/v1/invitations/token/{$token}")->assertNotFound();
    });

    it('[FAM-06] changes a member to admin and then to viewer', function () {
        familyAs($this->golden->ayah);
        $ibu = $this->golden->ibu->id;

        $this->patchJson("/api/v1/members/{$ibu}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ibu')
            ->assertJsonPath('data.role', 'admin');
        $this->patchJson("/api/v1/members/{$ibu}", ['role' => 'viewer'])->assertOk()->assertJsonPath('data.role_label', 'Hanya lihat');

        expect(memberRoles())->toBe(['Ayah' => 'owner', 'Ibu' => 'viewer']);
    });

    it('[FAM-07] removes a member; their transactions stay and their access ends at once [BR-I04]', function () {
        familyAs($this->golden->ayah);

        $this->deleteJson("/api/v1/members/{$this->golden->ibu->id}")->assertNoContent();

        expect(memberRoles())->toBe(['Ayah' => 'owner']);
        $ibuExpenses = collect($this->getJson('/api/v1/transactions?user_id[]='.$this->golden->ibu->id)->json('data'));
        expect($ibuExpenses)->toHaveCount(3)
            ->and($ibuExpenses->pluck('created_by.name')->unique()->all())->toBe(['Ibu']);

        familyAs($this->golden->ibu->refresh());
        $this->getJson('/api/v1/wallets')->assertStatus(409)->assertJsonPath('code', 'no_family');
        $this->getJson('/api/v1/wallets', ['X-Family-Id' => $this->golden->family->id])->assertNotFound();
    });

    it('[FAM-08] lets a member leave the family [BR-I04]', function () {
        familyAs($this->golden->ibu);

        $this->postJson("/api/v1/families/{$this->golden->family->id}/leave")
            ->assertOk()
            ->assertJsonPath('message', 'Kamu telah keluar dari keluarga.')
            ->assertJsonPath('data.current_family_id', null)
            ->assertJsonPath('data.families', []);

        expect(Transaction::query()->withoutGlobalScopes()->where('created_by', $this->golden->ibu->id)->whereNull('deleted_at')->count())->toBe(3);
    });

    it('[FAM-09] switches the active family and then reads that family', function () {
        $second = createFamily($this->golden->ayah, 'Keluarga Kedua');
        familyAs($this->golden->ayah);

        $this->putJson("/api/v1/families/{$second->id}/switch")
            ->assertOk()
            ->assertJsonPath('data.current_family_id', $second->id)
            ->assertJsonCount(2, 'data.families');
        expect($this->golden->ayah->refresh()->current_family_id)->toBe($second->id);
        $this->getJson('/api/v1/wallets')->assertOk()->assertJsonPath('meta.total_balance', 0);

        $this->putJson("/api/v1/families/{$this->golden->family->id}/switch")->assertOk()->assertJsonPath('data.current_family_id', $this->golden->family->id);
        $this->getJson('/api/v1/wallets')->assertJsonPath('meta.total_balance', GoldenFamily::EXPECTED['total_balance']);
    });
});

describe('negative', function () {
    it('[FAM-21] does not let members or viewers manage members or invitations [BR-B09]', function () {
        $viewer = addMember($this->golden->family, FamilyRole::Viewer);

        foreach ([$this->golden->ibu, $viewer] as $user) {
            familyAs($user);
            $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'viewer'])->assertForbidden();
            $this->getJson('/api/v1/invitations')->assertForbidden();
            $this->patchJson("/api/v1/members/{$this->golden->ayah->id}", ['role' => 'viewer'])->assertForbidden();
            $this->deleteJson("/api/v1/members/{$this->golden->ayah->id}")->assertForbidden();
        }

        expect(invitationFor('adik@contoh.com'))->toBeNull();
        Notification::assertNothingSent();
    });

    it('[FAM-22] rejects inviting an existing member, a bad email or the owner role [BR-I02] [BR-I03]', function (array $input, string $field, ?string $message) {
        familyAs($this->golden->ayah);

        $response = $this->postJson('/api/v1/invitations', $input)->assertUnprocessable()->assertJsonValidationErrors($field);
        if ($message !== null) {
            $response->assertJsonPath("errors.{$field}.0", $message);
        }

        Notification::assertNothingSent();
    })->with([
        'existing member' => [fn () => ['email' => test()->golden->ibu->email, 'role' => 'member'], 'email', 'Orang ini sudah menjadi anggota keluarga.'],
        'bad email' => [['email' => 'bukan-email', 'role' => 'member'], 'email', null],
        'owner role' => [['email' => 'adik@contoh.com', 'role' => 'owner'], 'role', 'Peran ini tidak bisa diberikan lewat undangan.'],
        'unknown role' => [['email' => 'adik@contoh.com', 'role' => 'raja'], 'role', null],
    ]);

    it('[FAM-23] does not let an admin create, promote or remove admins [BR-I03]', function () {
        $admin = addMember($this->golden->family, FamilyRole::Admin);
        $otherAdmin = addMember($this->golden->family, FamilyRole::Admin);
        familyAs($admin);

        $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Hanya pemilik yang bisa mengatur admin.')
            ->assertJsonPath('rule', 'BR-I03');
        $this->patchJson("/api/v1/members/{$this->golden->ibu->id}", ['role' => 'admin'])->assertUnprocessable()->assertJsonPath('rule', 'BR-I03');
        $this->deleteJson("/api/v1/members/{$otherAdmin->id}")->assertUnprocessable()->assertJsonPath('rule', 'BR-I03');

        // An admin may still manage members and viewers.
        $this->patchJson("/api/v1/members/{$this->golden->ibu->id}", ['role' => 'viewer'])->assertOk();
        expect(memberRoles())->toMatchArray(['Ibu' => 'viewer'])
            ->and(collect($this->getJson('/api/v1/members')->json('data'))->where('role', 'admin'))->toHaveCount(2);
    });

    it('[FAM-24] never changes or removes the owner [BR-I01]', function () {
        familyAs($this->golden->ayah);

        $this->patchJson("/api/v1/members/{$this->golden->ayah->id}", ['role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pemilik keluarga tidak bisa diubah perannya atau dikeluarkan.')
            ->assertJsonPath('rule', 'BR-I01');
        $this->deleteJson("/api/v1/members/{$this->golden->ayah->id}")->assertUnprocessable()->assertJsonPath('rule', 'BR-I01');
        $this->patchJson("/api/v1/members/{$this->golden->ibu->id}", ['role' => 'owner'])->assertUnprocessable()->assertJsonValidationErrors('role');

        expect(memberRoles())->toBe(['Ayah' => 'owner', 'Ibu' => 'member']);
    });

    it('[FAM-25] does not let the owner leave [BR-I01]', function () {
        familyAs($this->golden->ayah);

        $this->postJson("/api/v1/families/{$this->golden->family->id}/leave")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Pemilik tidak bisa keluar dari keluarga. Pindahkan kepemilikan terlebih dahulu.')
            ->assertJsonPath('rule', 'BR-I01');

        expect(memberRoles())->toMatchArray(['Ayah' => 'owner']);
    });

    it('[FAM-26] refuses an invitation for another email, an expired one or a used one [BR-I02]', function () {
        familyAs($this->golden->ayah);
        $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'viewer'])->assertCreated();
        $token = invitationFor('adik@contoh.com')->token;
        $adik = User::factory()->create(['name' => 'Adik', 'email' => 'adik@contoh.com']);
        $stranger = User::factory()->create(['email' => 'orang@lain.com']);

        // Another email.
        familyAs($stranger);
        $this->getJson("/api/v1/invitations/token/{$token}")
            ->assertOk()
            ->assertJsonPath('data.can_accept', false)
            ->assertJsonPath('data.problem', 'Undangan ini untuk alamat email lain. Masuk dengan email yang diundang.');
        $this->postJson("/api/v1/invitations/token/{$token}/accept")->assertUnprocessable()->assertJsonPath('rule', 'BR-I02');

        // Used once, then not again.
        familyAs($adik);
        $this->postJson("/api/v1/invitations/token/{$token}/accept")->assertOk();
        $this->postJson("/api/v1/invitations/token/{$token}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Undangan ini sudah dipakai.');

        // Expired after 7 days.
        familyAs($this->golden->ayah);
        $this->postJson('/api/v1/invitations', ['email' => 'kakak@contoh.com', 'role' => 'member'])->assertCreated();
        $late = invitationFor('kakak@contoh.com')->token;
        $kakak = User::factory()->create(['name' => 'Kakak', 'email' => 'kakak@contoh.com']);
        $this->travelTo('2026-11-08 12:00:00');
        familyAs($kakak);
        $this->postJson("/api/v1/invitations/token/{$late}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Undangan sudah kedaluwarsa. Minta undangan baru ke pengundang.');

        familyAs($this->golden->ayah);
        expect(array_keys(memberRoles()))->toEqualCanonicalizing(['Ayah', 'Ibu', 'Adik']);
    });

    it('[FAM-27] treats families, members and invitations of others as not found [BR-I06]', function () {
        $neighbour = User::factory()->create(['name' => 'Tetangga']);
        $other = createFamily($neighbour, 'Tetangga');
        familyAs($neighbour);
        $this->postJson('/api/v1/invitations', ['email' => 'adik@contoh.com', 'role' => 'viewer'])->assertCreated();
        $foreignInvitation = invitationFor('adik@contoh.com');

        familyAs($this->golden->ayah);
        $this->putJson("/api/v1/families/{$other->id}/switch")->assertNotFound();
        $this->postJson("/api/v1/families/{$other->id}/leave")->assertNotFound();
        $this->patchJson("/api/v1/members/{$neighbour->id}", ['role' => 'viewer'])->assertNotFound();
        $this->deleteJson("/api/v1/members/{$neighbour->id}")->assertNotFound();
        $this->deleteJson("/api/v1/invitations/{$foreignInvitation->id}")->assertNotFound();

        expect($this->golden->ayah->refresh()->current_family_id)->toBe($this->golden->family->id)
            ->and(invitationFor('adik@contoh.com'))->not->toBeNull();
    });

    it('[FAM-28] answers 404 for an unknown invitation token', function () {
        familyAs(User::factory()->create());

        $this->getJson('/api/v1/invitations/token/tidak-ada')->assertNotFound();
        $this->postJson('/api/v1/invitations/token/tidak-ada/accept')->assertNotFound();
    });
});
