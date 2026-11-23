<?php

namespace App\Livewire\Admin\Leads;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Prospek & Quotation: proyek yang masih dalam tahap intake dan negosiasi. Hanya admin. */
#[Layout('layouts::admin')]
class Index extends Component
{
    use WithPagination;

    /** Status yang dianggap prospek (sebelum harga disepakati). */
    public const PIPELINE = [ProjectStatus::Lead, ProjectStatus::Quoted, ProjectStatus::Negotiating];

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function render(): View
    {
        Gate::authorize('admin');
        $adminId = auth()->id();

        $leads = Project::query()
            ->whereIn('projects.status', array_map(fn (ProjectStatus $s) => $s->value, self::PIPELINE))
            ->with(['client:id,name', 'latestQuotation'])
            ->withCount(['orderChats as unread_chats_count' => fn ($q) => $q->whereNull('read_at')->where('sender_id', '!=', $adminId)])
            ->orderByDesc('projects.created_at')
            ->orderByDesc('projects.id')
            ->paginate(10);

        return view('livewire.admin.leads.index', ['leads' => $leads])->title(__('quotations.leads.title'));
    }
}
