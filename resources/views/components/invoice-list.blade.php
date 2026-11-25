@props(['invoices'])

@if ($invoices->isEmpty())
    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('quotations.no_invoices') }}</p>
@else
    <ul class="divide-y divide-slate-200 dark:divide-slate-800">
        @foreach ($invoices as $invoice)
            <li wire:key="invoice-{{ $invoice->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                <div>
                    <span class="font-medium text-slate-900 dark:text-slate-50">{{ $invoice->invoice_number }}</span>
                    <span class="block text-xs text-slate-600 dark:text-slate-400">
                        {{ $invoice->type->label() }}
                        @if ($invoice->due_date) · {{ __('quotations.due') }} {{ $invoice->due_date->translatedFormat('d M Y') }} @endif
                    </span>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <span class="tabular-nums">{{ $invoice->money()->format() }}</span>
                    <x-badge-status :status="$invoice->status" />
                    <a href="{{ route('admin.invoices.print', $invoice->id) }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex min-h-11 items-center rounded-md border border-slate-300 px-3 py-1 text-xs font-medium lg:min-h-0 lg:px-2.5 text-slate-700 hover:border-growth hover:text-growth dark:border-slate-700 dark:text-slate-200"
                       aria-label="{{ __('projects.invoice.print_invoice', ['number' => $invoice->invoice_number]) }}">
                        {{ __('projects.invoice.print') }}
                    </a>
                </div>
            </li>
        @endforeach
    </ul>
@endif
