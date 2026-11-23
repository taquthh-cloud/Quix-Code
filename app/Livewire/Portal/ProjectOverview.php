<?php

namespace App\Livewire\Portal;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\RevisionTicket;
use Illuminate\Support\Facades\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::app')]
class ProjectOverview extends Component
{
    #[Locked]
    public string $projectId;

    public string $title = '';

    public string $description = '';

    public string $category = 'revision';

    public string $priority = 'medium';

    public function mount(string $project): void
    {
        $this->projectId = $project;
        $this->authorize('viewAny', [RevisionTicket::class, $this->project]);
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    public function submitRequest(): void
    {
        $project = $this->project;
        $this->authorize('create', [RevisionTicket::class, $project]);

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ]);

        $project->tickets()->create([
            ...$validated,
            'created_by' => auth()->id(),
        ]);

        $this->reset('title', 'description');
        $this->category = TicketCategory::Revision->value;
        $this->priority = TicketPriority::Medium->value;
        unset($this->tickets);

        session()->flash('portal-request-created', true);
    }

    #[Computed]
    public function tickets()
    {
        return RevisionTicket::query()
            ->visibleTo(auth()->user())
            ->where('project_id', $this->projectId)
            ->with('creator')
            ->latest()
            ->get();
    }

    public function render(): View
    {
        $project = $this->project;
        $this->authorize('viewAny', [RevisionTicket::class, $project]);
        $this->authorize('viewAny', [Invoice::class, $project]);

        return view('livewire.portal.project-overview', [
            'project' => $project,
            'milestones' => $project->milestones,
            'invoices' => Invoice::query()
                ->visibleTo(auth()->user())
                ->where('project_id', $project->id)
                ->with('payments')
                ->orderBy('created_at')
                ->get(),
            'tickets' => $this->tickets,
        ])->title($project->title);
    }
}
