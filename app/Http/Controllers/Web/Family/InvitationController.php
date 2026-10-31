<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Family;

use App\Exceptions\Family\InvitationRuleException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Family\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accepting an invitation happens outside any family context: the invitee is
 * not a member yet, so the invitation is found by token (BR-I07).
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitations,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->invitations->findByToken($token);
        abort_if($invitation === null, 404);

        /** @var User $user */
        $user = $request->user();

        try {
            $this->invitations->assertAcceptable($invitation, $user);
            $problem = null;
        } catch (InvitationRuleException $e) {
            $problem = $e->userMessage();
        }

        return Inertia::render('family/AcceptInvitation', [
            'token' => $token,
            'familyName' => $invitation->family?->name,
            'roleLabel' => $invitation->role->label(),
            'email' => $invitation->email,
            'problem' => $problem,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        abort_if($this->invitations->findByToken($token) === null, 404);

        /** @var User $user */
        $user = $request->user();
        $family = $this->invitations->accept($token, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Selamat datang di {$family->name}!"]);

        return redirect()->route('dashboard');
    }
}
