<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ke klien: Invoice DP terbit setelah penawaran disetujui. */
class DownPaymentInvoiceNotification extends Notification
{
    public function __construct(public readonly Invoice $invoice, public readonly Project $project) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.dp_invoice.subject', ['number' => $this->invoice->invoice_number, 'project' => $this->project->title]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(__('mail.dp_invoice.intro', ['project' => $this->project->title]))
            ->line(__('mail.dp_invoice.number', ['number' => $this->invoice->invoice_number]))
            ->line(__('mail.dp_invoice.amount', ['amount' => $this->invoice->money()->format()]))
            ->line($this->invoice->due_date !== null
                ? __('mail.dp_invoice.due', ['date' => $this->invoice->due_date->translatedFormat('j F Y')])
                : __('mail.dp_invoice.no_due'))
            ->action(__('mail.dp_invoice.action'), route('portal.projects.negotiation', $this->project->id))
            ->line(__('mail.dp_invoice.outro'))
            ->salutation(__('mail.salutation'));
    }
}
