<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.nav.billing') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.billing.subtitle') }}</p>
        </div>
        <flux:button variant="ghost" :href="route('admin.exports', ['type' => 'invoices', 'q' => $search, 'status' => $status])">{{ __('admin.search_page.export_invoices') }}</flux:button>
    </div>

    <div class="grid gap-3 sm:grid-cols-[1fr_16rem]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('admin.billing.search')" :aria-label="__('admin.billing.search')" />
        <flux:select wire:model.live="status" :aria-label="__('projects.invoice.status_filter')">
            <flux:select.option value="">{{ __('admin.billing.all_statuses') }}</flux:select.option>
            @foreach ($statuses as $invoiceStatus)
                <flux:select.option :value="$invoiceStatus->value">{{ $invoiceStatus->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-table :empty="$invoices->isEmpty()" :empty-title="__('admin.billing.empty')" :empty-description="__('admin.billing.empty_hint')">
        <x-slot:head>
            <tr>
                <th>{{ __('admin.billing.invoice') }}</th>
                <th>{{ __('admin.billing.project') }}</th>
                <th>{{ __('projects.invoice.type') }}</th>
                <th>{{ __('projects.invoice.status') }}</th>
                <th class="text-right">{{ __('projects.invoice.total') }}</th>
                <th></th>
            </tr>
        </x-slot:head>

        @foreach ($invoices as $invoice)
            <tr wire:key="billing-{{ $invoice->id }}">
                <td class="font-medium text-slate-900 dark:text-slate-50">
                    {{ $invoice->invoice_number }}
                    <span class="block text-xs font-normal text-slate-600 dark:text-slate-400">{{ $invoice->issued_at?->translatedFormat('d M Y') }}</span>
                </td>
                <td>
                    <a href="{{ route('admin.projects.show', $invoice->project_id) }}" wire:navigate class="font-medium hover:text-growth hover:underline">{{ $invoice->project->title }}</a>
                    <span class="block text-xs text-slate-600 dark:text-slate-400">{{ $invoice->project->client?->name }}</span>
                </td>
                <td>{{ $invoice->type->label() }}</td>
                <td><x-badge-status :status="$invoice->status" /></td>
                <td class="whitespace-nowrap text-right tabular-nums">{{ $invoice->money()->format() }}</td>
                <td class="text-right">
                    <div class="flex justify-end gap-2">
                        <flux:button size="sm" :href="route('admin.invoices.print', ['invoice' => $invoice->id])" target="_blank" rel="noopener noreferrer">{{ __('projects.invoice.print') }}</flux:button>
                        <flux:button size="sm" variant="ghost" :href="route('admin.projects.invoices', ['project' => $invoice->project_id])" wire:navigate>{{ __('admin.billing.manage') }}</flux:button>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-table>

    {{ $invoices->links() }}
</div>
