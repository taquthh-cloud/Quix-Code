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
                <div class="flex items-center gap-3">
                    <span class="tabular-nums">{{ $invoice->money()->format() }}</span>
                    <x-badge-status :status="$invoice->status" />
                </div>
            </li>
        @endforeach
    </ul>
@endif
