<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke klien: penawaran terkirim akan segera berakhir (pengingat T3-06). */
class QuotationExpiringNotification extends Notification
{
    public function __construct(public readonly Quotation $quotation, public readonly Project $project) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = max(0, (int) $this->quotation->daysLeft());

        return (new MailMessage)
            ->subject(__('mail.quotation_expiring.subject', ['version' => $this->quotation->version, 'project' => $this->project->title]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.quotation_expiring.intro', [
                'version' => $this->quotation->version,
                'project' => $this->project->title,
                'date' => $this->quotation->valid_until?->translatedFormat('j F Y'),
                'when' => trans_choice('mail.quotation_expiring.days', $days, ['count' => $days]),
            ]))
            ->action(__('mail.quotation_expiring.action'), route('portal.projects.negotiation', $this->project->id))
            ->line(__('mail.quotation_expiring.outro'))
            ->salutation(__('mail.salutation'));
    }
}
