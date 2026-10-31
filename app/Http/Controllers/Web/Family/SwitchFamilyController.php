<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Family;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Family\FamilyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SwitchFamilyController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
    ) {}

    public function __invoke(Request $request, int $family): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Families the user does not belong to are "not found" (BR-I06).
        abort_if($this->families->switchTo($user, $family) === null, 404);

        return redirect()->route('dashboard');
    }
}
