<?php

namespace App\Livewire\Chat;

use App\Enums\ActivityAction;
use App\Enums\ProjectStatus;
use App\Models\OrderChat;
use App\Models\Project;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Chat negosiasi terkait proyek (admin <-> klien), polling Livewire (Bagian 7: Infrastruktur).
 * Staf tidak pernah boleh melihatnya: ChatPolicy dicek di mount, di setiap aksi, dan di setiap render.
 */
class Room extends Component
{
    public const MAX_LENGTH = 2000;

    /** Pesan yang ditampilkan: yang terbaru saja. */
    public const WINDOW = 100;

    /** Interval polling adaptif (detik): rapat saat percakapan aktif, merenggang saat sepi. */
    public const POLL_FAST = 5;

    public const POLL_MEDIUM = 15;

    public const POLL_SLOW = 30;

    /** Jumlah poll tanpa pesan baru sebelum interval naik ke tingkat berikutnya. */
    public const FAST_TICKS = 6;

    public const MEDIUM_TICKS = 18;

    #[Locked]
    public string $projectId;

    /** ID pesan terbaru yang sudah dirender, dan jumlah poll berturut-turut tanpa pesan baru. Hanya server yang boleh mengubahnya. */
    #[Locked]
    public int $lastSeenId = 0;

    #[Locked]
    public int $idleTicks = 0;

    public string $message = '';

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        $this->authorize('viewAny', [OrderChat::class, $this->project()]);
    }

    /** Interval poll untuk jumlah poll sepi berturut-turut: 5 dtk -> 15 dtk -> 30 dtk. */
    public static function intervalFor(int $idleTicks): int
    {
        return match (true) {
            $idleTicks < self::FAST_TICKS => self::POLL_FAST,
            $idleTicks < self::MEDIUM_TICKS => self::POLL_MEDIUM,
            default => self::POLL_SLOW,
        };
    }

    /** Dimuat ulang tiap panggilan lewat visibleTo: properti publik bisa dimanipulasi dari browser. */
    private function project(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    public function send(): void
    {
        $user = auth()->user();
        $project = $this->project();
        $this->authorize('create', [OrderChat::class, $project]);

        $this->message = trim($this->message);

        $this->validate(
            ['message' => ['required', 'string', 'max:'.self::MAX_LENGTH]],
            [],
            ['message' => __('chat.fields.message')],
        );

        $sent = RateLimiter::attempt('chat:'.$user->id, 20, function () use ($user, $project) {
            DB::transaction(function () use ($user, $project) {
                OrderChat::create([
                    'project_id' => $project->id,
                    'sender_id' => $user->id,
                    'message' => $this->message,
                ]);

                // Klien membuka chat saat penawaran terkirim -> negosiasi (Bagian 5).
                $fresh = Project::query()->lockForUpdate()->find($project->id);

                if ($user->isClient() && $fresh?->status === ProjectStatus::Quoted) {
                    // Syarat transisi: penawaran terbaru masih terkirim dan berlaku. Bila sudah kedaluwarsa
                    // (T3-06) atau ada draf versi baru, pesan tetap tersimpan dan status proyek tidak berubah.
                    try {
                        $fresh->transitionTo(ProjectStatus::Negotiating, $user);
                        ActivityLogger::changed(ActivityAction::StatusChanged, $fresh, ProjectStatus::Quoted, ProjectStatus::Negotiating, $user);
                    } catch (InvalidArgumentException) {
                        // dibiarkan: negosiasi lewat chat tetap boleh
                    }
                }
            });
        }, 60);

        if (! $sent) {
            $this->addError('message', __('chat.errors.too_fast'));

            return;
        }

        $this->reset('message');
        $this->idleTicks = 0; // percakapan sedang aktif: kembali ke polling rapat
        $this->dispatch('chat-sent');
    }

    public function render(): View
    {
        $user = auth()->user();
        $project = $this->project();
        $this->authorize('viewAny', [OrderChat::class, $project]);

        // Pesan lawan bicara yang baru terbaca. Satu UPDATE; tidak ada query per pesan.
        OrderChat::query()
            ->where('project_id', $project->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = OrderChat::query()
            ->visibleTo($user)
            ->where('order_chats.project_id', $project->id)
            ->with('sender:id,name,role')
            ->orderByDesc('order_chats.id')
            ->limit(self::WINDOW)
            ->get()
            ->reverse()
            ->values();

        // Pesan baru (dari siapa pun) menjaga polling tetap rapat; render tanpa pesan baru menambah hitungan sepi.
        $latestId = (int) ($messages->last()?->id ?? 0);

        if ($latestId !== $this->lastSeenId) {
            $this->lastSeenId = $latestId;
            $this->idleTicks = 0;
        } else {
            $this->idleTicks++;
        }

        return view('livewire.chat.room', [
            'messages' => $messages,
            'pollSeconds' => self::intervalFor($this->idleTicks),
            'canSend' => $user->can('create', [OrderChat::class, $project]),
            'maxLength' => self::MAX_LENGTH,
        ]);
    }
}
