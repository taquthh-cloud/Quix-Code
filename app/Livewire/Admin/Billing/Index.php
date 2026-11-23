<?php

namespace App\Livewire\Admin\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
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
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(): void
    {
        $this->authorize('admin');
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
        $this->authorize('admin');
        $term = trim($this->search);
        $status = InvoiceStatus::tryFrom($this->status);

        $invoices = Invoice::query()
            ->with(['project.client:id,name'])
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $query->where(function ($search) use ($like): void {
                    $search->where('invoice_number', 'like', $like)
                        ->orWhereHas('project', fn ($project) => $project->where('title', 'like', $like)
                            ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $like)));
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->latest('issued_at')
            ->paginate(20);

        return view('livewire.admin.billing.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
        ])->title(__('admin.nav.billing'));
    }
}
