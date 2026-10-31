<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use App\Models\User;
use App\Services\User\AppearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

class AppearanceController extends Controller
{
    public function __construct(
        private readonly AppearanceService $appearance,
    ) {}

    /**
     * Save the user's light/dark preference to their account.
     */
    public function update(AppearanceUpdateRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $appearance = $this->appearance->update($user, $request->appearance())->appearance;

        // Keep the cookie in sync so the first paint after logout matches too.
        Cookie::queue('appearance', $appearance->value, 60 * 24 * 365);

        return back();
    }
}
