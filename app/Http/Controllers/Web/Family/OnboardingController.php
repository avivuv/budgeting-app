<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Family;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Family\FamilyRequest;
use App\Models\User;
use App\Services\Family\FamilyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function __construct(
        private readonly FamilyService $families,
    ) {}

    /**
     * First step after registering: create a family (the user becomes its owner).
     */
    public function create(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->families->familiesOf($user)->isNotEmpty() && ! $request->boolean('new')) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/Create', [
            'suggestedName' => 'Keluarga '.str($user->name)->explode(' ')->last(),
        ]);
    }

    public function store(FamilyRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $family = $this->families->create($request->toData(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$family->name} siap digunakan."]);

        return redirect()->route('dashboard');
    }
}
