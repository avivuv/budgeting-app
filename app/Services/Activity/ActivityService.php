<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Enums\TransactionType;
use App\Models\Activity;
use App\Models\Transaction;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Tenancy\TenantContext;
use App\Support\Money\MoneyFormatter;
use Carbon\CarbonImmutable;

/**
 * Human-readable change history from the activity log (BR-B14). Entries older
 * than the retention period (30 days) are gone by design.
 */
class ActivityService
{
    /** Fields shown in the history, in display order. */
    private const array TRANSACTION_FIELDS = [
        'type' => 'Jenis',
        'amount' => 'Nominal',
        'date' => 'Tanggal',
        'wallet_id' => 'Dompet',
        'to_wallet_id' => 'Dompet tujuan',
        'category_id' => 'Kategori',
        'note' => 'Catatan',
    ];

    public function __construct(
        private readonly ActivityRepositoryInterface $activities,
        private readonly UserRepositoryInterface $users,
        private readonly WalletRepositoryInterface $wallets,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @return list<array{event: string, title: string, who: string, when: string, at: string|null, changes: list<array{field: string, old: string, new: string}>}>
     */
    public function transactionHistory(Transaction $transaction): array
    {
        $entries = $this->activities->forSubject($this->tenant->id(), $transaction);

        $userNames = $this->users->namesByIds($this->ids($entries, fn (Activity $a) => [$a->causer_id]));
        $walletNames = $this->wallets->namesByIds($this->ids($entries, fn (Activity $a) => [
            ...$this->values($a, 'wallet_id'), ...$this->values($a, 'to_wallet_id'),
        ]));
        $categoryNames = $this->categories->namesByIds($this->ids($entries, fn (Activity $a) => $this->values($a, 'category_id')));

        $format = fn (string $field, mixed $value): string => match (true) {
            $value === null || $value === '' => '—',
            $field === 'amount' => MoneyFormatter::format((int) $value),
            $field === 'date' => CarbonImmutable::parse((string) $value)->translatedFormat('j M Y'),
            $field === 'type' => TransactionType::tryFrom((string) $value)?->label() ?? (string) $value,
            in_array($field, ['wallet_id', 'to_wallet_id'], true) => $walletNames[(int) $value] ?? 'Dompet dihapus',
            $field === 'category_id' => $categoryNames[(int) $value] ?? 'Kategori dihapus',
            default => (string) $value,
        };

        $history = [];

        foreach ($entries as $activity) {
            $changes = [];

            if ($activity->event === 'updated') {
                $old = $activity->attribute_changes['old'] ?? [];
                $new = $activity->attribute_changes['attributes'] ?? [];

                foreach (self::TRANSACTION_FIELDS as $field => $label) {
                    if (array_key_exists($field, $new) && ($old[$field] ?? null) !== $new[$field]) {
                        $changes[] = ['field' => $label, 'old' => $format($field, $old[$field] ?? null), 'new' => $format($field, $new[$field])];
                    }
                }
            }

            $history[] = [
                'event' => (string) $activity->event,
                'title' => match ($activity->event) {
                    'created' => 'mencatat transaksi',
                    'updated' => 'mengubah transaksi',
                    'deleted' => 'menghapus transaksi',
                    default => (string) $activity->description,
                },
                'who' => $userNames[(int) $activity->causer_id] ?? 'Sistem',
                'when' => $activity->created_at?->translatedFormat('j M Y H:i') ?? '',
                // Same moment, machine-readable, in the family's timezone (API clients).
                'at' => $activity->created_at?->copy()->setTimezone($this->tenant->get()->timezone)->toIso8601String(),
                'changes' => $changes,
            ];
        }

        return $history;
    }

    /**
     * @param  iterable<Activity>  $entries
     * @param  callable(Activity): array<int, mixed>  $pick
     * @return list<int>
     */
    private function ids(iterable $entries, callable $pick): array
    {
        $ids = [];

        foreach ($entries as $entry) {
            foreach ($pick($entry) as $id) {
                if (is_numeric($id)) {
                    $ids[(int) $id] = (int) $id;
                }
            }
        }

        return array_values($ids);
    }

    /**
     * Old and new values of one attribute in an activity.
     *
     * @return list<mixed>
     */
    private function values(Activity $activity, string $field): array
    {
        return [
            $activity->attribute_changes['old'][$field] ?? null,
            $activity->attribute_changes['attributes'][$field] ?? null,
        ];
    }
}
