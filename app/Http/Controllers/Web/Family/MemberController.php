<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Family;

use App\Enums\FamilyRole;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Family\InvitationService;
use App\Services\Family\MembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function __construct(
        private readonly MembershipService $members,
        private readonly InvitationService $invitations,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $canManage = Gate::allows('manage-family');

        return Inertia::render('family/Members', [
            'members' => $this->members->members()->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->membership?->role->value,
                'roleLabel' => $member->membership?->role->label(),
                'joined' => $member->membership?->joined_at->translatedFormat('j M Y'),
                'isSelf' => $member->id === $user->id,
            ])->values(),
            'invitations' => $canManage
                ? $this->invitations->pending()->map(fn (Invitation $invitation): array => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'roleLabel' => $invitation->role->label(),
                    'expires' => $invitation->expires_at->translatedFormat('j M Y'),
                    'expired' => $invitation->isExpired(),
                    'inviter' => $invitation->inviter?->name,
                ])->values()
                : [],
            'roles' => collect([FamilyRole::Admin, FamilyRole::Member, FamilyRole::Viewer])
                ->map(fn (FamilyRole $role): array => ['value' => $role->value, 'label' => $role->label()])
                ->values(),
            'canManage' => $canManage,
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        Gate::authorize('manage-family');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(FamilyRole::class)],
        ], [], ['email' => 'email', 'role' => 'peran']);

        /** @var User $user */
        $user = $request->user();
        $invitation = $this->invitations->invite($user, $validated['email'], FamilyRole::from($validated['role']));

        return $this->done("Undangan dikirim ke {$invitation->email}.");
    }

    public function revokeInvitation(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('manage-family');

        $this->invitations->revoke($invitation);

        return $this->done('Undangan dibatalkan.');
    }

    public function updateRole(Request $request, int $member): RedirectResponse
    {
        Gate::authorize('manage-family');

        $validated = $request->validate(['role' => ['required', Rule::enum(FamilyRole::class)]]);

        /** @var User $actor */
        $actor = $request->user();
        $this->members->changeRole($actor, $this->members->findMember($member), FamilyRole::from($validated['role']));

        return $this->done('Peran diperbarui.');
    }

    public function remove(Request $request, int $member): RedirectResponse
    {
        Gate::authorize('manage-family');

        /** @var User $actor */
        $actor = $request->user();
        $this->members->remove($actor, $this->members->findMember($member));

        return $this->done('Anggota dikeluarkan.');
    }

    public function leave(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->members->leave($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kamu telah keluar dari keluarga.']);

        return redirect()->route('dashboard');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('members.index');
    }
}
