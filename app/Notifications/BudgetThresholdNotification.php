<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A budget reached 80% or 100% (BR-F05). Sent at most once per threshold and
 * period; plain values only, so the queued notification needs no tenant.
 */
class BudgetThresholdNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $familyId,
        public readonly string $familyName,
        public readonly int $budgetId,
        public readonly string $categoryName,
        public readonly string $periodKey,
        public readonly string $periodLabel,
        public readonly int $threshold,
        public readonly string $spentFormatted,
        public readonly string $amountFormatted,
        public readonly string $percentageText,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function title(): string
    {
        return $this->threshold >= 100
            ? "Budget {$this->categoryName} terlampaui"
            : "Budget {$this->categoryName} sudah {$this->threshold}%";
    }

    public function message(): string
    {
        return "{$this->spentFormatted} dari {$this->amountFormatted} ({$this->percentageText}) terpakai di periode {$this->periodLabel}.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->title()} · {$this->familyName}")
            ->greeting('Halo!')
            ->line($this->message())
            ->action('Lihat budget', route('budgets.index', ['period' => $this->periodKey]))
            ->line('Notifikasi ini dikirim sekali untuk setiap ambang (80% dan 100%) per periode.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'budget_threshold',
            'family_id' => $this->familyId,
            'budget_id' => $this->budgetId,
            'threshold' => $this->threshold,
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => route('budgets.index', ['period' => $this->periodKey], absolute: false),
        ];
    }
}
