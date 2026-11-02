<?php

namespace App\Http\Middleware;

use App\Enums\FamilyRole;
use App\Models\Family;
use App\Models\User;
use App\Services\Family\FamilyService;
use App\Services\Tenancy\TenantContext;
use App\Services\User\AppearanceService;
use App\Services\User\NotificationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'appearance' => app(AppearanceService::class)
                ->resolve($request->user(), $request->cookie('appearance'))
                ->value,
            // Resolved lazily, after the "family" route middleware has set the tenant.
            'families' => fn () => $this->families($request),
            'family' => fn () => $this->currentFamily($request),
            // Badge of the notification bell (docs/07 §4b).
            'unreadNotifications' => fn () => $request->user() instanceof User
                ? app(NotificationService::class)->unreadCount($request->user())
                : 0,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, role: string}>
     */
    private function families(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return [];
        }

        return app(FamilyService::class)->familiesOf($user)
            ->map(fn (Family $family): array => [
                'id' => $family->id,
                'name' => $family->name,
                'role' => $family->membership?->role->value ?? FamilyRole::Viewer->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, role: string, role_label: string, period_start_day: int, can: array{write: bool, manage: bool}}|null
     */
    private function currentFamily(Request $request): ?array
    {
        $tenant = app(TenantContext::class);
        $user = $request->user();

        if (! $tenant->has() || ! $user instanceof User) {
            return null;
        }

        $family = $tenant->get();
        $role = app(FamilyService::class)->roleOf($family, $user) ?? FamilyRole::Viewer;

        return [
            'id' => $family->id,
            'name' => $family->name,
            'role' => $role->value,
            'role_label' => $role->label(),
            'period_start_day' => $family->period_start_day,
            'can' => [
                'write' => $role->canWrite(),
                'manage' => $role->canManage(),
            ],
        ];
    }
}
