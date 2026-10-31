<?php

use App\Enums\FamilyRole;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\FamilyInvitationNotification;
use App\Services\Family\FamilyService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $this->owner = User::factory()->create(['name' => 'Ayah']);
    $this->family = createFamily($this->owner, 'Keluarga Budi');
});

function inviteAs(User $inviter, string $email = 'Ibu@Contoh.com', string $role = 'member')
{
    return test()->actingAs($inviter)->post(route('members.invite'), ['email' => $email, 'role' => $role]);
}

it('emails an invitation link to the invitee', function () {
    inviteAs($this->owner)->assertRedirect(route('members.index'));

    useFamily($this->family);
    $invitation = Invitation::query()->sole();

    expect($invitation->email)->toBe('ibu@contoh.com')
        ->and($invitation->role)->toBe(FamilyRole::Member)
        ->and($invitation->expires_at->diffInDays(now(), true))->toEqualWithDelta(7, 0.01);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        FamilyInvitationNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'ibu@contoh.com'
            && str_contains($notification->toMail($notifiable)->actionUrl, $invitation->token),
    );
});

it('lets the invited email accept and join [BR-I02]', function () {
    inviteAs($this->owner);
    useFamily($this->family);
    $token = Invitation::query()->sole()->token;
    $ibu = User::factory()->create(['email' => 'ibu@contoh.com']);

    $this->actingAs($ibu)
        ->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page
            ->component('family/AcceptInvitation')
            ->where('familyName', 'Keluarga Budi')
            ->where('problem', null));

    $this->actingAs($ibu)->post(route('invitations.accept', $token))->assertRedirect(route('dashboard'));

    expect(app(FamilyService::class)->roleOf($this->family, $ibu))->toBe(FamilyRole::Member)
        ->and($ibu->fresh()->current_family_id)->toBe($this->family->id);
});

it('rejects other emails, reuse and expired invitations [BR-I02]', function () {
    inviteAs($this->owner);
    useFamily($this->family);
    $invitation = Invitation::query()->sole();
    $stranger = User::factory()->create(['email' => 'orang@lain.com']);
    $ibu = User::factory()->create(['email' => 'ibu@contoh.com']);

    $this->actingAs($stranger)->post(route('invitations.accept', $invitation->token))->assertSessionHasErrors('general');
    expect(app(FamilyService::class)->roleOf($this->family, $stranger))->toBeNull();

    $this->travel(8)->days();
    $this->actingAs($ibu)
        ->get(route('invitations.show', $invitation->token))
        ->assertInertia(fn (Assert $page) => $page->where('problem', 'Undangan sudah kedaluwarsa. Minta undangan baru ke pengundang.'));
    $this->actingAs($ibu)->post(route('invitations.accept', $invitation->token))->assertSessionHasErrors('general');

    $this->travelBack();
    $this->actingAs($ibu)->post(route('invitations.accept', $invitation->token))->assertRedirect(route('dashboard'));
    $this->actingAs($ibu)->post(route('invitations.accept', $invitation->token))->assertSessionHasErrors('general');
});

it('returns 404 for unknown tokens and sends guests to login [BR-I07]', function () {
    $this->actingAs($this->owner)->get(route('invitations.show', 'tidak-ada'))->assertNotFound();

    auth()->logout();
    $this->get(route('invitations.show', 'apa-saja'))->assertRedirect(route('login'));
});

it('refuses to invite existing members or as owner', function () {
    inviteAs($this->owner, $this->owner->email)->assertSessionHasErrors('email');
    inviteAs($this->owner, 'x@contoh.com', 'owner')->assertSessionHasErrors('role');
});

it('lets only the owner invite admins [BR-I03]', function () {
    $admin = addMember($this->family, FamilyRole::Admin);

    inviteAs($admin, 'a@contoh.com', 'admin')->assertSessionHasErrors('general');
    inviteAs($admin, 'a@contoh.com', 'member')->assertRedirect(route('members.index'));
    inviteAs($this->owner, 'b@contoh.com', 'admin')->assertRedirect(route('members.index'));
});

it('forbids members and viewers from inviting', function (FamilyRole $role) {
    inviteAs(addMember($this->family, $role))->assertForbidden();
})->with([FamilyRole::Member, FamilyRole::Viewer]);

it('replaces a pending invitation for the same email', function () {
    inviteAs($this->owner);
    inviteAs($this->owner, 'ibu@contoh.com', 'viewer');

    useFamily($this->family);
    expect(Invitation::query()->sole()->role)->toBe(FamilyRole::Viewer);
});

it('keeps invitations of other families out of reach [BR-I06]', function () {
    inviteAs($this->owner);
    useFamily($this->family);
    $invitation = Invitation::query()->sole();

    $neighbour = User::factory()->create();
    createFamily($neighbour, 'Tetangga');

    $this->actingAs($neighbour)->delete(route('members.invitations.destroy', $invitation->id))->assertNotFound();
});
