<?php

namespace App\Livewire\Admin\Projects;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\QuotationService;
use App\Support\QuotationComparison;
use DomainException;
use Flux\Flux;
use Illuminate\Support\Collection;
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

    /** Quotation yang sedang dibandingkan dengan versi sebelumnya (T3-02). */
    public ?string $comparingId = null;

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

    public function toggleCompare(string $quotationId): void
    {
        $this->authorize('viewAny', [Quotation::class, $this->project]);

        $this->comparingId = $this->comparingId === $quotationId ? null : $quotationId;
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

        $quotations = $project->quotations()->with('items')->withCount('items')->get();
        [$current, $previous] = $this->comparisonPair($quotations);

        return view('livewire.admin.projects.negotiation', [
            'project' => $project,
            'quotations' => $quotations,
            'comparison' => $current && $previous ? QuotationComparison::between($previous, $current, internal: true) : null,
            'comparisonCurrent' => $current,
            'comparisonPrevious' => $previous,
            'invoices' => Invoice::query()->where('project_id', $project->id)->orderBy('created_at')->get(),
            'canCreate' => auth()->user()->can('create', [Quotation::class, $project]) && ! $project->isCurrencyLocked(),
        ])->title(__('quotations.negotiation.title'));
    }

    /**
     * Pasangan [versi dipilih, versi sebelumnya] dari quotation proyek ini saja.
     *
     * @param  Collection<int, Quotation>  $quotations
     * @return array{0: ?Quotation, 1: ?Quotation}
     */
    private function comparisonPair($quotations): array
    {
        $current = $this->comparingId === null ? null : $quotations->firstWhere('id', $this->comparingId);
        $previous = $current?->version === null ? null : $quotations->where('version', '<', $current->version)->sortByDesc('version')->first();

        return [$current, $previous];
    }
}
