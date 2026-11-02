<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The web notification bell (docs/07 §4b): budget alerts and, later, receipts,
 * invitations and exports.
 */
class NotificationService
{
    public const int LIST_LIMIT = 50;

    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function unreadCount(User $user): int
    {
        return $this->users->unreadNotificationCount($user);
    }

    /**
     * @return list<array{id: string, title: string, message: string, url: string|null, read: bool, createdAt: string}>
     */
    public function latest(User $user): array
    {
        return array_values($this->users->notifications($user, self::LIST_LIMIT)
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => (string) $notification->id,
                'title' => (string) ($notification->data['title'] ?? 'Notifikasi'),
                'message' => (string) ($notification->data['message'] ?? ''),
                'url' => isset($notification->data['url']) ? (string) $notification->data['url'] : null,
                'read' => $notification->read_at !== null,
                'createdAt' => $notification->created_at?->diffForHumans() ?? '',
            ])
            ->all());
    }

    public function markRead(User $user, ?string $id = null): void
    {
        $this->users->markNotificationsRead($user, $id);
    }
}
