<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ActivityAction;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectNote;
use App\Models\User;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Project Control Room: ringkasan, status, progres, tim, dan milestone.
 *
 * Properti publik Livewire bisa dimanipulasi dari browser, jadi proyek selalu dimuat ulang lewat
 * Project::visibleTo() dan setiap aksi memanggil authorize() sendiri.
 */
#[Layout('layouts::admin')]
class Show extends Component
{
    #[Locked]
    public string $projectId;

    /** Id milestone yang sedang diubah; null = form tambah. Terkunci agar tidak bisa dipalsukan. */
    #[Locked]
    public ?int $editingMilestoneId = null;

    /** String agar input kosong dari browser tidak menyebabkan TypeError; divalidasi lalu di-cast. */
    public string $progress = '0';

    public string $statusReason = '';

    public string $noteBody = '';

    public string $staffId = '';

    public string $taskRole = '';

    public string $ms_title = '';

    public string $ms_target = '';

    public string $ms_due = '';

    public string $ms_assignee = '';

    public function mount(string $project): void
    {
        $this->projectId = $project;

        // visibleTo: staf yang tidak ditugaskan dan klien mendapat 404; staf menerima model tanpa kolom harga.
        $model = $this->project;
        $this->authorize('view', $model);

        $this->progress = (string) $model->progress_percent;
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    private function refreshProject(): void
    {
        unset($this->project);
        $this->progress = (string) $this->project->progress_percent;
    }

    // ------------------------------------------------------------------ status

    public function changeStatus(string $to): void
    {
        $project = $this->project;
        $this->authorize('transition', $project);

        $target = ProjectStatus::tryFrom($to);

        $isResume = $project->status === ProjectStatus::OnHold
            && $project->on_hold_from === $target?->value;

        if ($target === null || (! $isResume && ! $project->status->canTransitionTo($target))) {
            Flux::toast(variant: 'danger', text: __('projects.errors.invalid_transition'));

            return;
        }

        if (in_array($target, [ProjectStatus::OnHold, ProjectStatus::Cancelled], true)) {
            $this->validate(['statusReason' => ['required', 'string', 'max:1000']]);
        }

        try {
            DB::transaction(function () use ($project, $target) {
                $old = $project->status;
                $project->transitionTo($target, auth()->user(), $this->statusReason ?: null);

                if ($target === ProjectStatus::Closed) {
                    $project->forceFill(['completed_at' => now()])->save();
                }

                ActivityLogger::changed(ActivityAction::StatusChanged, $project, $old, $target);
            });
        } catch (InvalidArgumentException) {
            Flux::toast(variant: 'danger', text: __('projects.errors.invalid_transition'));

            return;
        }

        $this->refreshProject();
        $this->reset('statusReason');
        Flux::toast(variant: 'success', text: __('projects.toast.status_changed'));
    }

    // ------------------------------------------------------------------ progres

    public function updateProgress(): void
    {
        $project = $this->project;
        $this->authorize('update', $project);

        $this->validate(
            ['progress' => ['required', 'integer', 'between:0,100']],
            [],
            ['progress' => __('projects.fields.progress')],
        );

        $old = (int) $project->progress_percent;

        $new = (int) $this->progress;

        if ($old !== $new) {
            DB::transaction(function () use ($project, $old, $new) {
                $project->setProgress($new);
                ActivityLogger::changed(ActivityAction::ProgressChanged, $project, $old, $project->progress_percent);
            });
        }

        $this->refreshProject();
        Flux::toast(variant: 'success', text: __('projects.toast.progress_updated'));
    }

    // ------------------------------------------------------------------ tim

    public function assignStaff(): void
    {
        $project = $this->project;
        $this->authorize('assignStaff', $project);

        $data = $this->validate([
            'staffId' => [
                'required',
                Rule::exists('users', 'id')->where('role', UserRole::Staff->value),
                Rule::unique('project_assignments', 'staff_id')->where('project_id', $project->id),
            ],
            'taskRole' => ['nullable', 'string', 'max:50'],
        ], [], [
            'staffId' => __('projects.fields.staff_id'),
            'taskRole' => __('projects.fields.task_role'),
        ]);

        $taskRole = trim($data['taskRole'] ?? '') ?: null;

        DB::transaction(function () use ($project, $data, $taskRole) {
            $project->staff()->attach((int) $data['staffId'], ['task_role' => $taskRole]);
            ActivityLogger::log(ActivityAction::StaffAssigned, $project, [
                'staff_id' => (int) $data['staffId'],
                'task_role' => $taskRole,
            ]);
        });

        $this->reset('staffId', 'taskRole');
        $this->refreshProject();
        Flux::toast(variant: 'success', text: __('projects.toast.staff_assigned'));
    }

    public function unassignStaff(int $staffId): void
    {
        $project = $this->project;
        $this->authorize('assignStaff', $project);

        DB::transaction(function () use ($project, $staffId) {
            if ($project->staff()->detach($staffId) === 0) {
                return;
            }

            // Milestone miliknya jadi tanpa penanggung jawab, supaya staf itu tidak masih bisa menggesernya.
            ProjectMilestone::query()
                ->where('project_id', $project->id)
                ->where('assigned_to', $staffId)
                ->update(['assigned_to' => null]);

            ActivityLogger::log(ActivityAction::StaffUnassigned, $project, ['staff_id' => $staffId]);
        });

        $this->refreshProject();
        Flux::toast(variant: 'success', text: __('projects.toast.staff_unassigned'));
    }

    // ------------------------------------------------------------------ milestone

    public function editMilestone(int $id): void
    {
        $milestone = $this->findMilestone($id);
        $this->authorize('update', $milestone);

        $this->editingMilestoneId = $milestone->id;
        $this->ms_title = $milestone->title;
        $this->ms_target = (string) $milestone->target_progress;
        $this->ms_due = $milestone->due_date?->format('Y-m-d') ?? '';
        $this->ms_assignee = $milestone->assigned_to ? (string) $milestone->assigned_to : '';
        $this->resetValidation();
    }

    public function cancelMilestoneEdit(): void
    {
        $this->resetMilestoneForm();
    }

    public function saveMilestone(): void
    {
        $project = $this->project;
        $milestone = $this->editingMilestoneId ? $this->findMilestone($this->editingMilestoneId) : null;

        $milestone
            ? $this->authorize('update', $milestone)
            : $this->authorize('create', [ProjectMilestone::class, $project]);

        $data = $this->validate([
            'ms_title' => ['required', 'string', 'max:255'],
            'ms_target' => ['required', 'integer', 'between:0,100'],
            'ms_due' => ['nullable', 'date'],
            // Admin yang sedang jeda hanya boleh mempertahankan penugasan milestone yang sudah dimilikinya.
            'ms_assignee' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where(function ($query) use ($project, $milestone) {
                        $query->where(function ($query) use ($milestone) {
                            $query->where('role', UserRole::Admin->value)
                                ->where('is_active', true)
                                ->where(function ($availability) use ($milestone) {
                                    $availability->where('is_available_for_projects', true);
                                    if ($milestone?->assigned_to) {
                                        $availability->orWhere('id', $milestone->assigned_to);
                                    }
                                });
                        })->orWhere(function ($query) use ($project) {
                            $query->where('role', UserRole::Staff->value)
                                ->whereIn('id', DB::table('project_assignments')
                                    ->select('staff_id')
                                    ->where('project_id', $project->id));
                        });
                    })),
            ],
        ], [], [
            'ms_title' => __('projects.fields.ms_title'),
            'ms_target' => __('projects.fields.ms_target'),
            'ms_due' => __('projects.fields.ms_due'),
            'ms_assignee' => __('projects.fields.ms_assignee'),
        ]);

        $attributes = [
            'title' => trim($data['ms_title']),
            'target_progress' => (int) $data['ms_target'],
            'due_date' => ($data['ms_due'] ?? '') ?: null,
            'assigned_to' => ($data['ms_assignee'] ?? '') !== '' ? (int) $data['ms_assignee'] : null,
        ];

        DB::transaction(function () use ($project, $milestone, $attributes) {
            if ($milestone === null) {
                ProjectMilestone::create($attributes + [
                    'project_id' => $project->id,
                    'sort_order' => (int) ProjectMilestone::query()->where('project_id', $project->id)->max('sort_order') + 1,
                ]);
                $project->completeReachedMilestones();

                return;
            }

            $targetChanged = (int) $milestone->target_progress !== $attributes['target_progress'];
            $milestone->update($attributes);

            if ($targetChanged) {
                $project->completeReachedMilestones();
            }
        });

        $this->resetMilestoneForm();
        $this->refreshProject();
        Flux::toast(variant: 'success', text: __('projects.toast.milestone_saved'));
    }

    public function deleteMilestone(int $id): void
    {
        $milestone = $this->findMilestone($id);
        $this->authorize('delete', $milestone);

        $milestone->delete();

        if ($this->editingMilestoneId === $id) {
            $this->resetMilestoneForm();
        }

        $this->refreshProject();
        Flux::toast(variant: 'success', text: __('projects.toast.milestone_deleted'));
    }

    /** Geser urutan: tukar dengan tetangga lalu beri nomor ulang agar sort_order selalu unik. */
    public function moveMilestone(int $id, string $direction): void
    {
        $project = $this->project;
        $milestone = $this->findMilestone($id);
        $this->authorize('update', $milestone);

        $ids = $project->milestones()->pluck('id')->all();
        $index = array_search($milestone->id, $ids, true);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || ! isset($ids[$swapWith])) {
            return;
        }

        [$ids[$index], $ids[$swapWith]] = [$ids[$swapWith], $ids[$index]];

        DB::transaction(function () use ($ids, $project) {
            foreach ($ids as $position => $milestoneId) {
                ProjectMilestone::query()
                    ->where('project_id', $project->id)
                    ->whereKey($milestoneId)
                    ->update(['sort_order' => $position]);
            }
        });

        $this->refreshProject();
    }

    /** Geser/koreksi status: admin, atau staf pemilik milestone (MilestonePolicy::move) saat proyek dalam tahap pengerjaan. */
    public function setMilestoneStatus(int $id, string $status): void
    {
        $project = $this->project;
        $milestone = $this->findMilestone($id);
        $milestone->setRelation('project', $project);

        $this->authorize('move', $milestone);

        $target = MilestoneStatus::tryFrom($status);
        abort_if($target === null, 422);

        if (! auth()->user()->isAdmin() && ! $project->status->isWorkable()) {
            Flux::toast(variant: 'danger', text: __('projects.errors.not_workable'));

            return;
        }

        $from = $milestone->status;
        if ($from !== $target) {
            DB::transaction(function () use ($milestone, $from, $target): void {
                $milestone->forceFill([
                    'status' => $target,
                    'completed_at' => $target === MilestoneStatus::Done ? now() : null,
                ])->save();
                ActivityLogger::changed(ActivityAction::MilestoneStatusChanged, $milestone, $from, $target);
            });
        }

        $this->refreshProject();
        Flux::toast(variant: 'success', text: __('projects.toast.milestone_status'));
    }

    // ------------------------------------------------------------------ hapus

    public function deleteProject(): void
    {
        $project = $this->project;
        $this->authorize('delete', $project);

        $project->delete();
        session()->flash('status', __('projects.toast.deleted'));

        $this->redirectRoute('admin.projects.index', navigate: true);
    }

    // ------------------------------------------------------------------ catatan internal

    public function addNote(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $this->validate([
            'noteBody' => ['required', 'string', 'max:5000'],
        ]);
        $project = $this->project;

        DB::transaction(function () use ($project, $data): void {
            $note = ProjectNote::query()->create([
                'project_id' => $project->id,
                'user_id' => auth()->id(),
                'body' => trim($data['noteBody']),
            ]);
            ActivityLogger::log(ActivityAction::ProjectNoteCreated, $note, ['project_id' => $project->id]);
        });

        $this->reset('noteBody');
        Flux::toast(variant: 'success', text: __('projects.notes.saved'));
    }

    public function deleteNote(int $noteId): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $note = ProjectNote::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($noteId);
        $note->delete();

        Flux::toast(variant: 'success', text: __('projects.notes.deleted'));
    }

    // ------------------------------------------------------------------ util

    private function findMilestone(int $id): ProjectMilestone
    {
        return ProjectMilestone::query()
            ->where('project_id', $this->project->id)
            ->findOrFail($id);
    }

    private function resetMilestoneForm(): void
    {
        $this->editingMilestoneId = null;
        $this->reset('ms_title', 'ms_target', 'ms_due', 'ms_assignee');
        $this->resetValidation();
    }

    /** Teks ringkas perubahan untuk kartu riwayat. */
    private function describe(ActivityLog $log): string
    {
        $old = $log->properties['old'] ?? null;
        $new = $log->properties['new'] ?? null;

        return match ($log->action) {
            ActivityAction::StatusChanged->value => (ProjectStatus::tryFrom((string) $old)?->label() ?? $old)
                .' → '.(ProjectStatus::tryFrom((string) $new)?->label() ?? $new),
            ActivityAction::ProgressChanged->value => "{$old}% → {$new}%",
            ActivityAction::ProjectCreated->value => (string) ($log->properties['title'] ?? ''),
            default => '',
        };
    }

    public function render(): View
    {
        $user = auth()->user();
        $project = $this->project;

        $project->load([
            $user->isAdmin() ? 'client:id,name,email,whatsapp' : 'client:id,name',
            'assignments:id,project_id,staff_id,task_role',
            'staff:id,name,role',
            'milestones.assignee:id,name',
            'milestones.acceptances.client:id,name',
        ]);

        $assignedAdminIds = $project->milestones->pluck('assigned_to')->filter()->unique()->values();
        $projectAdmins = User::query()
            ->where('role', UserRole::Admin->value)
            ->whereIn('id', $assignedAdminIds)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
        $currentMilestoneAssigneeId = $this->editingMilestoneId
            ? $project->milestones->firstWhere('id', $this->editingMilestoneId)?->assigned_to
            : null;
        $milestoneAssignees = User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->where(function ($query) use ($currentMilestoneAssigneeId) {
                $query->where('is_available_for_projects', true);
                if ($currentMilestoneAssigneeId) {
                    $query->orWhere('id', $currentMilestoneAssigneeId);
                }
            })
            ->get(['id', 'name', 'role'])
            ->concat($project->staff)
            ->sortBy('name')
            ->values();

        // Semua pemeriksaan hak geser memakai Policy yang sama, tanpa query tambahan per baris.
        $workable = $user->isAdmin() || $project->status->isWorkable();
        $movable = [];
        foreach ($project->milestones as $milestone) {
            $milestone->setRelation('project', $project);
            $movable[$milestone->id] = $workable && Gate::allows('move', $milestone);
        }

        $isAdmin = $user->isAdmin();

        $activities = $isAdmin
            ? ActivityLog::query()->with('user:id,name')
                ->where('subject_type', Project::class)
                ->where('subject_id', $project->id)
                ->orderByDesc('id')->limit(10)->get()
            : collect();

        return view('livewire.admin.projects.show', [
            'project' => $project,
            'projectAdmins' => $projectAdmins,
            'milestoneAssignees' => $milestoneAssignees,
            'movable' => $movable,
            'transitions' => $isAdmin
                ? ($project->status === ProjectStatus::OnHold && $project->on_hold_from
                    ? [ProjectStatus::from($project->on_hold_from), ProjectStatus::Cancelled]
                    : $project->status->allowedTransitions())
                : [],
            'availableStaff' => $isAdmin
                ? User::query()->where('role', UserRole::Staff->value)
                    ->whereNotIn('id', $project->assignments->pluck('staff_id'))
                    ->orderBy('name')->get(['id', 'name'])
                : collect(),
            'activities' => $activities,
            'notes' => $isAdmin ? $project->notes()->with('author:id,name')->limit(20)->get() : collect(),
            'descriptions' => $activities->mapWithKeys(fn (ActivityLog $log) => [$log->id => $this->describe($log)]),
            'milestoneStatuses' => MilestoneStatus::cases(),
        ])->title($project->title);
    }
}
