<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="{{ route('portal.projects.show', $project->id) }}" wire:navigate class="inline-flex min-h-11 items-center text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ __('payments.back') }}</a>
        <flux:heading size="xl">{{ __('payments.title') }}</flux:heading>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $project->title }}</p>
    </div>

    @forelse ($invoices as $invoice)
        @php
            $currency = $invoice->currency;
            $remaining = \App\Support\Money::of((int) $invoice->amount, $currency)->subtract(\App\Support\Money::of((int) $invoice->paid_amount, $currency));
            $lastPayment = $invoice->payments->first();
            $canUpload = auth()->user()->can('uploadProof', $invoice);
        @endphp

        <x-card wire:key="portal-invoice-{{ $invoice->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="break-words font-heading text-base font-semibold text-slate-900 dark:text-slate-50">{{ $invoice->invoice_number }}</h2>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">
                        {{ $invoice->type->label() }}
                        @if ($invoice->due_date) · {{ __('payments.due') }} {{ $invoice->due_date->translatedFormat('d M Y') }} @endif
                    </p>
                </div>
                <x-badge-status :status="$invoice->status" />
            </div>

            <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('payments.total') }}</dt>
                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ $invoice->money()->format() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('payments.paid') }}</dt>
                    <dd class="tabular-nums text-slate-900 dark:text-slate-50">{{ \App\Support\Money::of((int) $invoice->paid_amount, $currency)->format() }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500 dark:text-slate-400">{{ __('payments.remaining') }}</dt>
                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ $remaining->format() }}</dd>
                </div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('admin.invoices.print', $invoice->id) }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 text-sm font-medium text-slate-700 hover:border-growth hover:text-growth dark:border-slate-700 dark:text-slate-200"
                   aria-label="{{ __('projects.invoice.print_invoice', ['number' => $invoice->invoice_number]) }}">
                    {{ __('payments.download_pdf') }}
                </a>
                @if ($canUpload && $payingInvoiceId !== $invoice->id)
                    <flux:button variant="primary" class="min-h-11" wire:click="startUpload('{{ $invoice->id }}')">{{ __('payments.upload_proof') }}</flux:button>
                @endif
            </div>

            @if ($invoice->status === \App\Enums\InvoiceStatus::AwaitingVerification)
                <p role="status" class="mt-4 rounded-lg bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:bg-sky-500/15 dark:text-sky-200">{{ __('payments.awaiting_note') }}</p>
            @elseif ($invoice->status === \App\Enums\InvoiceStatus::Paid)
                <p role="status" class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-500/15 dark:text-emerald-200">{{ __('payments.paid_note') }}</p>
            @elseif ($canUpload && $lastPayment?->status === \App\Enums\PaymentStatus::Rejected)
                <p role="alert" class="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:bg-rose-500/15 dark:text-rose-200">{{ __('payments.resubmit_hint') }}</p>
            @endif

            @if ($payingInvoiceId === $invoice->id)
                <form wire:submit="submitProof" class="mt-4 flex flex-col gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                    <h3 class="font-medium text-slate-900 dark:text-slate-50">{{ __('payments.form_title') }}</h3>

                    <flux:input wire:model="amount" inputmode="decimal" autocomplete="off" required :label="__('payments.fields.amount')" :description="__('payments.hints.amount', ['remaining' => $remaining->format()])" />
                    <flux:input wire:model="paidAt" type="date" required :max="now()->toDateString()" :label="__('payments.fields.paid_at')" />
                    <flux:input wire:model="bankName" required maxlength="100" :label="__('payments.fields.bank_name')" />
                    <flux:input wire:model="senderName" required maxlength="160" :label="__('payments.fields.sender_name')" />
                    <flux:input wire:model="referenceNumber" maxlength="100" :label="__('payments.fields.reference_number')" />

                    <div class="flex flex-col gap-2">
                        <flux:input wire:model="proof" type="file" required accept="image/jpeg,image/png,image/webp,application/pdf" :label="__('payments.fields.proof')" :description="__('payments.hints.proof', ['max' => \App\Support\SecureUpload::maxMegabytes('proof')])" />
                        <div wire:loading wire:target="proof" class="text-sm text-slate-600 dark:text-slate-400">{{ __('payments.uploading') }}</div>
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        <flux:button type="button" class="min-h-11" wire:click="cancelUpload">{{ __('payments.cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" class="min-h-11" wire:loading.attr="disabled" wire:target="proof,submitProof">{{ __('payments.submit') }}</flux:button>
                    </div>
                </form>
            @endif

            <div class="mt-6">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-50">{{ __('payments.history') }}</h3>
                @forelse ($invoice->payments as $payment)
                    <article wire:key="portal-payment-{{ $payment->id }}" class="mt-3 rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium tabular-nums text-slate-900 dark:text-slate-50">{{ \App\Support\Money::of((int) $payment->amount, $currency)->format() }}</span>
                            <x-badge-status :status="$payment->status" />
                        </div>
                        <p class="mt-1 break-words text-xs text-slate-600 dark:text-slate-400">
                            {{ $payment->paid_at?->translatedFormat('d M Y') }}
                            @if ($payment->bank_name) · {{ $payment->bank_name }} @endif
                            @if ($payment->reference_number) · {{ $payment->reference_number }} @endif
                        </p>
                        @if ($payment->status === \App\Enums\PaymentStatus::Rejected && $payment->rejection_reason)
                            <p class="mt-2 break-words text-xs text-rose-700 dark:text-rose-300"><span class="font-medium">{{ __('payments.rejected_reason') }}:</span> {{ $payment->rejection_reason }}</p>
                        @endif
                        @if ($payment->proof_path)
                            <a href="{{ route('admin.payments.proof', ['payment' => $payment->id]) }}" class="mt-2 inline-flex min-h-11 items-center text-xs font-medium text-growth hover:underline">{{ __('payments.view_proof') }}</a>
                        @endif
                    </article>
                @empty
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('payments.no_payments') }}</p>
                @endforelse
            </div>
        </x-card>
    @empty
        <x-card>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('payments.empty') }}</p>
        </x-card>
    @endforelse
</div>
