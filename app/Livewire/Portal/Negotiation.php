<?php

namespace App\Livewire\Portal;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Services\QuotationService;
use App\Support\QuotationComparison;
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
#[Layout('layouts::portal')]
class Negotiation extends Component
{
    #[Locked]
    public string $projectId;

    /** Langkah 1 persetujuan: klien membuka panel konfirmasi untuk satu quotation. */
    public ?string $confirmingId = null;

    /** Langkah 2: klien menyatakan sudah membaca rincian dan ketentuan. */
    public bool $agreed = false;

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

    public function startAccept(string $quotationId): void
    {
        $this->authorize('respond', $this->findQuotation($quotationId));

        $this->confirmingId = $quotationId;
        $this->agreed = false;
        $this->resetErrorBag();
    }

    public function cancelAccept(): void
    {
        $this->reset('confirmingId', 'agreed');
        $this->resetErrorBag();
    }

    public function accept(string $quotationId): void
    {
        $quotation = $this->findQuotation($quotationId);
        $this->authorize('respond', $quotation);

        // Persetujuan harus lewat dua langkah: buka konfirmasi, lalu centang pernyataan.
        if ($this->confirmingId !== $quotationId || ! $this->agreed) {
            $this->addError('agreed', __('quotations.errors.confirm_required'));

            return;
        }

        try {
            app(QuotationService::class)->accept($quotation, auth()->user(), request()->ip(), request()->userAgent());
        } catch (DomainException $e) {
            Flux::toast(variant: 'danger', text: __($e->getMessage()));

            return;
        }

        $this->reset('confirmingId', 'agreed');
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

        // Hanya penawaran yang boleh dilihat klien (tanpa draf), sehingga perbandingan tidak membocorkan draf.
        $quotations = Quotation::query()
            ->visibleTo($user)
            ->where('quotations.project_id', $project->id)
            ->with(['items', 'project']) // project dibaca QuotationPolicy::respond per kartu
            ->orderByDesc('quotations.version')
            ->get();

        $current = $this->comparingId === null ? null : $quotations->firstWhere('id', $this->comparingId);
        $previous = $current === null ? null : $quotations->where('version', '<', $current->version)->sortByDesc('version')->first();

        return view('livewire.portal.negotiation', [
            'project' => $project,
            'quotations' => $quotations,
            'comparison' => $current && $previous ? QuotationComparison::between($previous, $current) : null,
            'comparisonPrevious' => $previous,
            'termsText' => app(QuotationService::class)->termsText(app()->getLocale()),
            'termsVersion' => app(QuotationService::class)->currentTermsVersion(),
            'invoices' => Invoice::query()->visibleTo($user)->where('invoices.project_id', $project->id)->orderBy('created_at')->get(),
        ])->title(__('quotations.negotiation.title'));
    }
}
