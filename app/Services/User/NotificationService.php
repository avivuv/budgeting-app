<?php

declare(strict_types=1);

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /**
     * REST API feed (docs/api-contracts/notifications.md): what the notification
     * is about, so web and Android can open the right screen.
     *
     * @return list<array<string, mixed>>
     */
    public function feed(User $user): array
    {
        return array_values($this->users->notifications($user, self::LIST_LIMIT)
            ->map(fn (DatabaseNotification $notification): array => [
                'id' => (string) $notification->id,
                'kind' => isset($notification->data['kind']) ? (string) $notification->data['kind'] : null,
                'title' => (string) ($notification->data['title'] ?? 'Notifikasi'),
                'message' => (string) ($notification->data['message'] ?? ''),
                'family_id' => isset($notification->data['family_id']) ? (int) $notification->data['family_id'] : null,
                'subject' => array_filter([
                    'budget_id' => $notification->data['budget_id'] ?? null,
                    'period' => $notification->data['period'] ?? null,
                ], fn (mixed $value): bool => $value !== null) ?: null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->toIso8601String(),
                'created_at_human' => $notification->created_at?->diffForHumans(),
            ])
            ->all());
    }

    /**
     * Mark one notification (or all) as read. An id of someone else's
     * notification is "not found".
     */
    public function markRead(User $user, ?string $id = null): void
    {
        if ($id !== null && ! $this->users->ownsNotification($user, $id)) {
            throw (new ModelNotFoundException)->setModel(DatabaseNotification::class, [$id]);
        }

        $this->users->markNotificationsRead($user, $id);
    }
}
