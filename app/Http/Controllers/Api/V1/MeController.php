<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
        private readonly TenantContext $tenant,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'current_family_id' => $this->tenant->id(),
                'families' => $this->families->familiesOf($user)->map(fn (Family $family): array => [
                    'id' => $family->id,
                    'name' => $family->name,
                    'role' => $family->membership?->role->value,
                ])->values(),
            ],
        ]);
    }
}
