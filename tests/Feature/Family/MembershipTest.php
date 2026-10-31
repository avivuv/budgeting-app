<?php

use App\Enums\FamilyRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Family\FamilyService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->owner = User::factory()->create(['name' => 'Ayah']);
    $this->family = createFamily($this->owner);
    $this->roleOf = fn (User $user) => app(FamilyService::class)->roleOf($this->family, $user);
});

it('lists members with the owner first', function () {
    addMember($this->family, FamilyRole::Viewer, User::factory()->create(['name' => 'Adik']));
    addMember($this->family, FamilyRole::Member, User::factory()->create(['name' => 'Ibu']));

    $this->actingAs($this->owner)
        ->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('family/Members')
            ->where('members.0.name', 'Ayah')
            ->where('members.0.role', 'owner')
            ->where('members.1.name', 'Ibu')
            ->where('members.2.name', 'Adik')
            ->where('canManage', true));
});

it('never changes or removes the owner [BR-I01]', function () {
    $admin = addMember($this->family, FamilyRole::Admin);

    $this->actingAs($admin)->patch(route('members.update', $this->owner->id), ['role' => 'member'])->assertSessionHasErrors('general');
    $this->actingAs($admin)->delete(route('members.destroy', $this->owner->id))->assertSessionHasErrors('general');
    $this->actingAs($this->owner)->post(route('family.leave'))->assertSessionHasErrors('general');

    expect(($this->roleOf)($this->owner))->toBe(FamilyRole::Owner);
});

it('lets only the owner manage admins [BR-I03]', function () {
    $admin = addMember($this->family, FamilyRole::Admin);
    $otherAdmin = addMember($this->family, FamilyRole::Admin);
    $member = addMember($this->family, FamilyRole::Member);

    $this->actingAs($admin)->delete(route('members.destroy', $otherAdmin->id))->assertSessionHasErrors('general');
    $this->actingAs($admin)->patch(route('members.update', $member->id), ['role' => 'admin'])->assertSessionHasErrors('general');
    $this->actingAs($admin)->patch(route('members.update', $member->id), ['role' => 'viewer'])->assertRedirect(route('members.index'));
    $this->actingAs($this->owner)->patch(route('members.update', $otherAdmin->id), ['role' => 'member'])->assertRedirect(route('members.index'));

    expect(($this->roleOf)($member))->toBe(FamilyRole::Viewer)
        ->and(($this->roleOf)($otherAdmin))->toBe(FamilyRole::Member);
});

it('keeps transactions of removed members attributed to them [BR-I04]', function () {
    $ibu = addMember($this->family, FamilyRole::Member, User::factory()->create(['name' => 'Ibu']));
    useFamily($this->family);
    $transaction = Transaction::factory()
        ->expense(25_000, Wallet::query()->firstOrFail(), Category::query()->where('name', 'Transportasi')->firstOrFail())
        ->create(['created_by' => $ibu->id]);

    $this->actingAs($this->owner)->delete(route('members.destroy', $ibu->id))->assertRedirect(route('members.index'));

    useFamily($this->family);
    expect(($this->roleOf)($ibu))->toBeNull()
        ->and($transaction->fresh()->created_by)->toBe($ibu->id);

    $this->actingAs($ibu)->get(route('dashboard'))->assertRedirect(route('onboarding.create'));
});

it('lets non-owners leave the family', function () {
    $member = addMember($this->family, FamilyRole::Member);

    $this->actingAs($member)->post(route('family.leave'))->assertRedirect(route('dashboard'));

    expect(($this->roleOf)($member))->toBeNull();
});

it('returns 404 for users outside the family [BR-I06]', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->owner)->patch(route('members.update', $stranger->id), ['role' => 'viewer'])->assertNotFound();
});

it('forbids members from managing others', function () {
    $member = addMember($this->family, FamilyRole::Member);
    $other = addMember($this->family, FamilyRole::Member);

    $this->actingAs($member)->delete(route('members.destroy', $other->id))->assertForbidden();
});
