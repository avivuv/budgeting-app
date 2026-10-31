<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\ApiTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function __construct(
        private readonly ApiTokenService $tokens,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $this->tokens->list($user)->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'can_write' => in_array(ApiTokenService::WRITE, $token->abilities ?? [], true),
                'last_used' => $token->last_used_at?->diffForHumans() ?? 'Belum pernah',
                'expires' => $token->expires_at?->translatedFormat('j M Y') ?? '-',
            ])->values(),
            // Shown once, right after creation.
            'newToken' => $request->session()->get('new_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'can_write' => ['boolean'],
        ], [], ['name' => 'nama token']);

        /** @var User $user */
        $user = $request->user();
        $token = $this->tokens->create($user, $validated['name'], (bool) ($validated['can_write'] ?? false));

        return redirect()->route('api-tokens.index')->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($this->tokens->revoke($user, $token), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Token dicabut.']);

        return redirect()->route('api-tokens.index');
    }
}
