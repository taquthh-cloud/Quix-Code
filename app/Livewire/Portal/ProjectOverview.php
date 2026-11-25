<?php

namespace App\Livewire\Portal;

use App\Enums\ActivityAction;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectGitCommit;
use App\Models\ProjectMilestone;
use App\Models\RevisionTicket;
use App\Support\ActivityLogger;
use App\Support\ProjectNextActions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts::portal')]
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

    /**
     * T4-12. Klien menerima hasil milestone yang sudah selesai. Baris milestone dikunci di transaksi
     * dan diperiksa ulang, jadi dua tab atau klik ganda hanya menghasilkan satu penerimaan.
     */
    public function acceptMilestone(int $milestoneId): void
    {
        $project = $this->project();
        $accepted = DB::transaction(function () use ($project, $milestoneId): ?string {
            $milestone = ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->findOrFail($milestoneId);
            $milestone->setRelation('project', $project);

            if ($milestone->acceptances()->exists()) {
                return null; // sudah diterima (tab lain): tidak ada yang berubah
            }

            $this->authorize('accept', $milestone);

            $acceptance = $milestone->acceptances()->create([
                'client_id' => auth()->id(),
                'accepted_at' => now(),
                'ip' => request()->ip(),
            ]);

            ActivityLogger::log(ActivityAction::MilestoneAccepted, $milestone, [
                'acceptance_id' => $acceptance->getKey(),
                'project_id' => $project->id,
                'title' => $milestone->title,
            ]);

            return $milestone->title;
        });

        if ($accepted !== null) {
            session()->flash('portal-milestone-accepted', $accepted);
        }
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

    /** @return EloquentCollection<int, ProjectMilestone> */
    private function milestones(Project $project): EloquentCollection
    {
        /** @var EloquentCollection<int, ProjectMilestone> $milestones */
        $milestones = $project->milestones()->with('acceptances')->get();

        foreach ($milestones as $milestone) {
            $milestone->setRelation('project', $project);
        }

        return $milestones;
    }

    /**
     * T4-03. Linimasa pembaruan pengerjaan (diisi webhook di Tahap 5). Hash dan email penulis
     * tidak pernah diambil, jadi tidak bisa bocor ke tampilan klien.
     *
     * @return Collection<int, ProjectGitCommit>
     */
    private function commits(): Collection
    {
        return ProjectGitCommit::query()
            ->select(['id', 'project_id', 'commit_message', 'progress_recorded', 'pushed_at'])
            ->where('project_id', $this->projectId)
            ->orderByDesc('pushed_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        $project = $this->project;
        $this->authorize('viewAny', [RevisionTicket::class, $project]);
        $this->authorize('viewAny', [Invoice::class, $project]);

        return view('livewire.portal.project-overview', [
            'project' => $project,
            'milestones' => $this->milestones($project),
            'invoices' => Invoice::query()
                ->visibleTo(auth()->user())
                ->where('project_id', $project->id)
                ->with('payments')
                ->orderBy('created_at')
                ->get(),
            'tickets' => $this->tickets,
            'commits' => $this->commits(),
            'actions' => ProjectNextActions::for($project),
        ])->title($project->title);
    }
}
