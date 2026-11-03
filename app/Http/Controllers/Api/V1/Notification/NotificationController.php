<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Notification\ReadNotificationRequest;
use App\Models\User;
use App\Services\User\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's notifications, across their families
 * (docs/api-contracts/notifications.md).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $this->notifications->feed($user),
            'meta' => ['unread_count' => $this->notifications->unreadCount($user)],
        ]);
    }

    /**
     * Mark one notification (opened from the list) or all of them as read.
     */
    public function read(ReadNotificationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->notifications->markRead($user, $request->notificationId());

        return response()->json(['meta' => ['unread_count' => $this->notifications->unreadCount($user)]]);
    }
}
