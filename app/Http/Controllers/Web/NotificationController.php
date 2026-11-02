<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\User\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own notifications; nothing family-scoped to authorise.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('notifications/Index', [
            'notifications' => $this->notifications->latest($user),
        ]);
    }

    /**
     * Mark one as read (opened from the list) or all of them.
     */
    public function read(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $id = $request->string('id')->toString();

        $this->notifications->markRead($user, $id !== '' ? $id : null);

        return back();
    }
}
