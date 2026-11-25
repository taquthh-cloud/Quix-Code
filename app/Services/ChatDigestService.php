<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\OrderChat;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ChatDigestNotification;

/**
 * Digest email untuk pesan chat yang belum dibaca (T3-09). Bukan satu email per pesan: pesan baru diberi
 * waktu DELAY_MINUTES untuk dibaca di aplikasi, lalu semua pesan yang tertunda dari satu pengirim di satu
 * proyek dirangkum jadi satu email. Pesan yang sudah masuk digest ditandai notified_at.
 */
class ChatDigestService
{
    public const DELAY_MINUTES = 10;

    public function __construct(private readonly Notifier $notifier) {}

    /** @return int jumlah email digest yang terkirim */
    public function sendDue(): int
    {
        $cutoff = now()->subMinutes(self::DELAY_MINUTES);

        $groups = OrderChat::query()
            ->whereNull('read_at')
            ->whereNull('notified_at')
            ->with('sender:id,role')
            ->orderBy('id')
            ->get(['id', 'project_id', 'sender_id', 'created_at'])
            ->groupBy(fn (OrderChat $chat) => $chat->project_id.'|'.($chat->sender?->isClient() ? 'client' : 'admin'));

        $sent = 0;

        foreach ($groups as $key => $chats) {
            // Pesan tertua di kelompok ini belum cukup lama: tunggu putaran berikutnya.
            if ($chats->min('created_at')->gt($cutoff)) {
                continue;
            }

            [$projectId, $senderSide] = explode('|', $key);

            // Klaim dulu (idempoten bila command berjalan tumpang tindih); email gagal tidak diulang.
            $claimed = OrderChat::query()
                ->whereIn('id', $chats->pluck('id'))
                ->whereNull('read_at')
                ->whereNull('notified_at')
                ->update(['notified_at' => now()]);

            if ($claimed === 0) {
                continue;
            }

            $project = Project::query()->with('client')->find($projectId);

            if ($project === null) {
                continue;
            }

            $forAdmin = $senderSide === 'client';

            $recipients = $forAdmin
                ? User::query()->where('role', UserRole::Admin->value)->where('is_active', true)->get()
                : collect([$project->client]);

            foreach ($recipients as $recipient) {
                if ($this->notifier->deliver($recipient, new ChatDigestNotification($project, $claimed, $forAdmin), 'chat_digest')) {
                    $sent++;
                }
            }
        }

        return $sent;
    }
}
