<?php

namespace App\Livewire\Admin\Projects;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\QuotationService;
use DomainException;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Quotation & chat negosiasi satu proyek, sisi admin. Staf ditolak (QuotationPolicy::viewAny). */
#[Layout('layouts::admin')]
class Negotiation extends Component
{
    #[Locked]
    public string $projectId;

    public function mount(string $project): void
    {
        $this->projectId = $project;
        $this->authorize('viewAny', [Quotation::class, $this->project]);
    }

    #[Computed]
    public function project(): Project
    {
        return Project::query()->visibleTo(auth()->user())->findOrFail($this->projectId);
    }

    public function newVersion(string $quotationId): void
    {
        $project = $this->project;
        $this->authorize('create', [Quotation::class, $project]);

        $source = Quotation::query()->where('project_id', $project->id)->with('items')->findOrFail($quotationId);

        try {
            $draft = app(QuotationService::class)->newVersionFrom($source);
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        }

        $this->redirectRoute('admin.quotations.edit', ['project' => $project->id, 'quotation' => $draft->id], navigate: true);
    }

    public function render(): View
    {
        $project = $this->project;
        $this->authorize('viewAny', [Quotation::class, $project]);

        $project->load('client:id,name');

        return view('livewire.admin.projects.negotiation', [
            'project' => $project,
            'quotations' => $project->quotations()->withCount('items')->get(),
            'invoices' => Invoice::query()->where('project_id', $project->id)->orderBy('created_at')->get(),
            'canCreate' => auth()->user()->can('create', [Quotation::class, $project]) && ! $project->isCurrencyLocked(),
        ])->title(__('quotations.negotiation.title'));
    }
}
