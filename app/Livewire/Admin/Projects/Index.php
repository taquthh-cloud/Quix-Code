<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Daftar proyek. Data selalu lewat Project::visibleTo(), jadi staf hanya melihat proyek yang ditugaskan dan tanpa harga. */
#[Layout('layouts::admin')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Project::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        $term = trim($this->search);
        $status = ProjectStatus::tryFrom($this->status);

        $projects = Project::query()
            ->visibleTo($user)
            ->with('client:id,name')
            ->withCount('assignments')
            ->when($term !== '', function ($query) use ($term) {
                $like = '%'.addcslashes($term, '\\%_').'%';

                $query->where(function ($q) use ($like) {
                    $q->where('projects.title', 'like', $like)
                        ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like));
                });
            })
            ->when($status, fn ($query) => $query->where('projects.status', $status->value))
            ->orderByDesc('projects.created_at')
            ->orderByDesc('projects.id')
            ->paginate(10);

        return view('livewire.admin.projects.index', [
            'projects' => $projects,
            'statuses' => ProjectStatus::cases(),
            'filtered' => $term !== '' || $status !== null,
        ])->title(__('projects.title'));
    }
}
