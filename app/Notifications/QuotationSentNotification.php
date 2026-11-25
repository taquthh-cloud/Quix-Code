<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke klien: penawaran baru. Hanya total yang tampil, tidak pernah operasional atau margin. */
class QuotationSentNotification extends Notification
{
    public function __construct(public readonly Quotation $quotation, public readonly Project $project) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('mail.quotation_sent.subject', ['version' => $this->quotation->version, 'project' => $this->project->title]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.quotation_sent.intro', ['version' => $this->quotation->version, 'project' => $this->project->title]))
            ->line(__('mail.quotation_sent.total', ['total' => $this->quotation->totalMoney()->format()]))
            ->line($this->quotation->valid_until !== null
                ? __('mail.quotation_sent.valid_until', ['date' => $this->quotation->valid_until->translatedFormat('j F Y')])
                : __('mail.quotation_sent.no_limit'))
            ->action(__('mail.quotation_sent.action'), route('portal.projects.negotiation', $this->project->id))
            ->line(__('mail.quotation_sent.outro'));

        return $mail->salutation(__('mail.salutation'));
    }
}
