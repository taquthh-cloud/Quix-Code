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

    #[Locked]
    public string $projectId;

    public string $message = '';

    public function mount(string $projectId): void
    {
        $this->projectId = $projectId;
        $this->authorize('viewAny', [OrderChat::class, $this->project()]);
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
                    $fresh->transitionTo(ProjectStatus::Negotiating);
                    ActivityLogger::changed(ActivityAction::StatusChanged, $fresh, ProjectStatus::Quoted, ProjectStatus::Negotiating, $user);
                }
            });
        }, 60);

        if (! $sent) {
            $this->addError('message', __('chat.errors.too_fast'));

            return;
        }

        $this->reset('message');
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

        return view('livewire.chat.room', [
            'messages' => $messages,
            'canSend' => $user->can('create', [OrderChat::class, $project]),
            'maxLength' => self::MAX_LENGTH,
        ]);
    }
}
