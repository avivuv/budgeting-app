<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Auth\Concerns\RespondsWithMe;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The signed-in user. Works without a family (current_family_id null), so
 * clients can send new users to onboarding (docs/api-contracts/auth.md).
 */
class MeController extends Controller
{
    use RespondsWithMe;

    public function __invoke(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();

        return $this->me($user);
    }
}
