<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Enums\FamilyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\MemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Models\User;
use App\Services\Family\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Members of the active family (docs/api-contracts/family.md).
 */
class MemberController extends Controller
{
    public function __construct(private readonly MembershipService $members) {}

    public function index(): AnonymousResourceCollection
    {
        return MemberResource::collection($this->members->members())->additional(['meta' => [
            'can_manage' => Gate::allows('manage-family'),
            'roles' => array_map(
                fn (FamilyRole $role): array => ['value' => $role->value, 'label' => $role->label()],
                [FamilyRole::Admin, FamilyRole::Member, FamilyRole::Viewer],
            ),
        ]]);
    }

    /**
     * BR-I01, BR-I03.
     */
    public function update(MemberRoleRequest $request, int $member): MemberResource
    {
        Gate::authorize('manage-family');

        /** @var User $actor */
        $actor = $request->user();
        $this->members->changeRole($actor, $this->members->findMember($member), $request->role());

        return new MemberResource($this->members->findMember($member));
    }

    /**
     * Their past transactions stay, still attributed to them (BR-I04).
     */
    public function destroy(Request $request, int $member): Response
    {
        Gate::authorize('manage-family');

        /** @var User $actor */
        $actor = $request->user();
        $this->members->remove($actor, $this->members->findMember($member));

        return response()->noContent();
    }
}
