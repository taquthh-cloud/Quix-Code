<?php

namespace App\Livewire\Admin\Team;

use App\Enums\ActivityAction;
use App\Enums\InvoiceStatus;
use App\Models\Project;
use App\Models\User;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::admin')]
class ClientPreview extends Component
{
    #[Locked]
    public int $clientId;

    #[Locked]
    public string $reason = '';

    public function mount(int $client): void
    {
        $this->authorize('admin');
        $this->clientId = $client;
        $preview = session()->get('admin.client_preview');

        abort_unless(
            is_array($preview)
            && (int) ($preview['user_id'] ?? 0) === $client
            && (int) ($preview['expires_at'] ?? 0) >= now()->timestamp,
            403,
        );

        $this->reason = (string) $preview['reason'];
    }

    public function endPreview(): void
    {
        $this->authorize('admin');
        $client = User::query()->where('role', 'client')->findOrFail($this->clientId);

        ActivityLogger::log(ActivityAction::ClientPreviewEnded, $client, [
            'reason' => $this->reason,
        ]);
        session()->forget('admin.client_preview');
        Flux::toast(variant: 'success', text: __('admin.team.preview_ended'));
        $this->redirectRoute('admin.team.index', navigate: true);
    }

    public function render(): View
    {
        $this->authorize('admin');
        $client = User::query()->where('role', 'client')->findOrFail($this->clientId);
        $projects = Project::query()
            ->select(['id', 'client_id', 'title', 'description', 'status', 'progress_percent', 'currency'])
            ->where('client_id', $client->id)
            ->with([
                'milestones' => fn ($query) => $query->orderBy('sort_order'),
                'invoices' => fn ($query) => $query
                    ->where('status', '!=', InvoiceStatus::Draft->value),
            ])
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.admin.team.client-preview', compact('client', 'projects'))
            ->title(__('admin.team.client_preview'));
    }
}
