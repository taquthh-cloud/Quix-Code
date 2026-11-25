<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email ringkasan pesan chat yang belum dibaca. Isi pesan sengaja tidak disertakan. */
class ChatDigestNotification extends Notification
{
    public function __construct(public readonly Project $project, public readonly int $count, public readonly bool $forAdmin) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $side = $this->forAdmin ? 'admin' : 'client';
        $url = $this->forAdmin
            ? route('admin.projects.negotiation', $this->project->id)
            : route('portal.projects.negotiation', $this->project->id);

        return (new MailMessage)
            ->subject(__("mail.chat_digest.subject_{$side}", ['project' => $this->project->title]))
            ->greeting(__('mail.greeting', ['name' => $notifiable->name]))
            ->line(trans_choice('mail.chat_digest.intro', $this->count, ['count' => $this->count, 'project' => $this->project->title]))
            ->line(__('mail.chat_digest.hint'))
            ->action(__('mail.chat_digest.action'), $url)
            ->salutation(__('mail.salutation'));
    }
}
