<?php

namespace App\Livewire\Portal;

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

/**
 * Menu "Quotation & Chat" di portal klien. Proyek dan quotation selalu dimuat lewat visibleTo,
 * jadi klien lain mendapat 404 dan draf tidak pernah terlihat.
 */
#[Layout('layouts::app')]
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

    public function accept(string $quotationId): void
    {
        $quotation = $this->findQuotation($quotationId);
        $this->authorize('respond', $quotation);

        try {
            app(QuotationService::class)->accept($quotation, auth()->user(), request()->ip());
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        }

        unset($this->project);
        Flux::toast(variant: 'success', text: __('quotations.toast.accepted'));
    }

    public function reject(string $quotationId): void
    {
        $quotation = $this->findQuotation($quotationId);
        $this->authorize('respond', $quotation);

        try {
            app(QuotationService::class)->reject($quotation, auth()->user());
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        }

        Flux::toast(variant: 'success', text: __('quotations.toast.rejected'));
    }

    private function findQuotation(string $id): Quotation
    {
        return Quotation::query()
            ->visibleTo(auth()->user())
            ->where('quotations.project_id', $this->projectId)
            ->findOrFail($id);
    }

    public function render(): View
    {
        $user = auth()->user();
        $project = $this->project;
        $this->authorize('viewAny', [Quotation::class, $project]);

        return view('livewire.portal.negotiation', [
            'project' => $project,
            'quotations' => Quotation::query()
                ->visibleTo($user)
                ->where('quotations.project_id', $project->id)
                ->with('items')
                ->orderByDesc('quotations.version')
                ->get(),
            'invoices' => Invoice::query()->visibleTo($user)->where('invoices.project_id', $project->id)->orderBy('created_at')->get(),
        ])->title(__('quotations.negotiation.title'));
    }
}
