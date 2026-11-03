<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Family\MembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Switch the active family or leave one (docs/api-contracts/family.md).
 * Both work on any family of the user, not only the active one.
 */
class FamilyController extends Controller
{
    use RespondsWithMe;

    public function __construct(
        private readonly FamilyService $families,
        private readonly MembershipService $members,
    ) {}

    public function switch(Request $request, int $family): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Families the user does not belong to are "not found" (BR-I06).
        abort_if($this->families->switchTo($user, $family) === null, 404);

        return $this->me($user, stored: true)->response();
    }

    /**
     * BR-I01: the owner cannot leave; past transactions stay theirs (BR-I04).
     */
    public function leave(Request $request, int $family): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->members->leaveFamily($user, $family), 404);

        return $this->me($user, stored: true)->additional(['message' => 'Kamu telah keluar dari keluarga.'])->response();
    }
}
