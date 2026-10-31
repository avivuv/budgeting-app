<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FamilyInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $familyName,
        private readonly string $inviterName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Undangan bergabung ke {$this->familyName}")
            ->greeting('Halo!')
            ->line("{$this->inviterName} mengundang kamu untuk mencatat keuangan bersama di {$this->familyName} sebagai {$this->invitation->role->label()}.")
            ->action('Terima undangan', route('invitations.show', $this->invitation->token))
            ->line('Undangan berlaku sampai '.$this->invitation->expires_at->translatedFormat('j F Y').'.')
            ->line('Jika kamu tidak mengenal pengundang, abaikan email ini.');
    }
}
