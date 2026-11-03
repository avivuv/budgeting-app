<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Exceptions\Family\InvitationRuleException;
use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\InvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Family\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Invitations of the active family, and the invited person's side by token
 * (BR-I02, BR-I03, BR-I07 — docs/api-contracts/family.md).
 */
class InvitationController extends Controller
{
    use RespondsWithMe;

    public function __construct(private readonly InvitationService $invitations) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('manage-family');

        return InvitationResource::collection($this->invitations->pending()->load('inviter'));
    }

    public function store(InvitationRequest $request): JsonResponse
    {
        Gate::authorize('manage-family');

        /** @var User $user */
        $user = $request->user();
        $invitation = $this->invitations->invite($user, $request->email(), $request->role());

        return (new InvitationResource($invitation->load('inviter')))
            ->additional(['message' => "Undangan dikirim ke {$invitation->email}."])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Invitation $invitation): Response
    {
        Gate::authorize('manage-family');

        $this->invitations->revoke($invitation);

        return response()->noContent();
    }

    /**
     * The invited person looks at the invitation before accepting; no family context (BR-I07).
     */
    public function show(Request $request, string $token): JsonResponse
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

        return response()->json(['data' => [
            'family_name' => $invitation->family?->name,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'can_accept' => $problem === null,
            'problem' => $problem,
        ]]);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        abort_if($this->invitations->findByToken($token) === null, 404);

        /** @var User $user */
        $user = $request->user();
        $family = $this->invitations->accept($token, $user);

        return $this->me($user, stored: true)->additional(['message' => "Selamat datang di {$family->name}!"])->response();
    }
}
