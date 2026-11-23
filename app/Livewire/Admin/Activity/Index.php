<?php

namespace App\Livewire\Admin\Activity;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\Quotation;
use App\Models\RevisionTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $projectId = '';

    public function mount(): void
    {
        $this->authorize('admin');
    }

    public function updatingAction(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function updatingProjectId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('admin');
        $projectId = $this->projectId;
        $action = ActivityAction::tryFrom($this->action);
        $userId = filter_var($this->userId, FILTER_VALIDATE_INT) ?: null;

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($action, fn (Builder $query) => $query->where('action', $action->value))
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($projectId !== '', fn (Builder $query) => $this->filterProject($query, $projectId))
            ->latest('id')
            ->paginate(25);

        return view('livewire.admin.activity.index', [
            'logs' => $logs,
            'actions' => ActivityAction::cases(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('title')->get(['id', 'title']),
        ])->title(__('admin.nav.activity'));
    }

    private function filterProject(Builder $query, string $projectId): Builder
    {
        return $query->where(function (Builder $subject) use ($projectId): void {
            $subject->where(fn (Builder $project) => $project
                ->where('subject_type', Project::class)
                ->where('subject_id', $projectId))
                ->orWhere(fn (Builder $invoice) => $invoice
                    ->where('subject_type', Invoice::class)
                    ->whereIn('subject_id', Invoice::query()->where('project_id', $projectId)->select('id')))
                ->orWhere(fn (Builder $quotation) => $quotation
                    ->where('subject_type', Quotation::class)
                    ->whereIn('subject_id', Quotation::query()->where('project_id', $projectId)->select('id')))
                ->orWhere(fn (Builder $ticket) => $ticket
                    ->where('subject_type', RevisionTicket::class)
                    ->whereIn('subject_id', RevisionTicket::query()->where('project_id', $projectId)->select('id')))
                ->orWhere(fn (Builder $note) => $note
                    ->where('subject_type', ProjectNote::class)
                    ->whereIn('subject_id', ProjectNote::query()->where('project_id', $projectId)->select('id')))
                ->orWhere(fn (Builder $payment) => $payment
                    ->where('subject_type', Payment::class)
                    ->whereIn('subject_id', Payment::query()
                        ->whereIn('invoice_id', Invoice::query()->where('project_id', $projectId)->select('id'))
                        ->select('id')));
        });
    }
}
