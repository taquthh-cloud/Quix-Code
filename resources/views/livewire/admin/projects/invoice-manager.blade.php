<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.projects.negotiation', $project->id) }}" wire:navigate class="text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ __('quotations.open') }}</a>
            <h1 class="mt-1 font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('projects.invoice.title') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $project->title }} · {{ $project->currency->label() }}</p>
        </div>
        <flux:button :href="route('admin.projects.negotiation', $project->id)" wire:navigate>{{ __('ui.cancel') }}</flux:button>
    </div>

    <div class="grid items-start gap-6 xl:grid-cols-5">
        <x-card :title="__('projects.invoice.create_title')" class="xl:col-span-3">
            <form wire:submit="createInvoice" class="flex flex-col gap-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model="type" :label="__('projects.invoice.type')">
                        @foreach ($types as $invoiceType)
                            <flux:select.option :value="$invoiceType->value">{{ $invoiceType->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="dueDate" type="date" :label="__('projects.invoice.due_date')" />
                </div>

                <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-2 dark:border-slate-800">
                    <h2 class="font-heading text-sm font-semibold text-slate-900 dark:text-slate-50">{{ __('projects.invoice.items') }}</h2>
                    <flux:button size="sm" type="button" icon="plus" wire:click="addItem">{{ __('projects.invoice.add_item') }}</flux:button>
                </div>

                <flux:error name="items" />
                <div class="flex flex-col gap-4">
                    @foreach ($items as $index => $item)
                        <fieldset wire:key="invoice-item-{{ $index }}" class="grid gap-3 rounded-card border border-slate-200 p-4 dark:border-slate-800 sm:grid-cols-12">
                            <legend class="sr-only">{{ __('projects.invoice.item_number', ['number' => $index + 1]) }}</legend>
                            <flux:input wire:model="items.{{ $index }}.title" :label="__('projects.invoice.description')" class="sm:col-span-12" />
                            <flux:input wire:model="items.{{ $index }}.qty" type="number" min="0.001" step="0.001" :label="__('projects.invoice.quantity')" class="sm:col-span-4" />
                            <flux:input wire:model="items.{{ $index }}.unit_price" type="number" min="0.01" step="{{ $project->currency->decimals() === 0 ? '1' : '0.01' }}" :label="__('projects.invoice.unit_price')" class="sm:col-span-6" />
                            <div class="flex items-end justify-end sm:col-span-2">
                                @if (count($items) > 1)
                                    <flux:button type="button" variant="ghost" icon="trash" :aria-label="__('projects.invoice.remove_item')" wire:click="removeItem({{ $index }})" />
                                @endif
                            </div>
                            <div class="sm:col-span-12">
                                <flux:error name="items.{{ $index }}.title" />
                                <flux:error name="items.{{ $index }}.qty" />
                                <flux:error name="items.{{ $index }}.unit_price" />
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <flux:error name="type" />
                <flux:error name="dueDate" />
                <div class="flex justify-end border-t border-slate-200 pt-4 dark:border-slate-800">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('projects.invoice.create') }}</flux:button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('projects.invoice.history')" class="xl:col-span-2">
            <flux:error name="payment" />
            <x-invoice-list :invoices="$invoices" />
            @foreach ($invoices as $invoice)
                @if ($invoice->status->value === 'draft')
                    <div wire:key="issue-invoice-{{ $invoice->id }}" class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-800">
                        <span class="text-xs text-slate-600 dark:text-slate-400">{{ $invoice->invoice_number }} · {{ __('projects.invoice.draft') }}</span>
                        <flux:button size="sm" variant="primary" wire:click="issueInvoice('{{ $invoice->id }}')" wire:confirm="{{ __('projects.invoice.confirm_issue') }}">{{ __('projects.invoice.issue') }}</flux:button>
                    </div>
                @endif
            @endforeach
            @foreach ($invoices as $invoice)
                @foreach ($invoice->payments->where('status', \App\Enums\PaymentStatus::Pending) as $payment)
                    <section wire:key="payment-review-{{ $payment->id }}" class="mt-4 rounded-card border border-sky-200 p-4 dark:border-sky-500/30">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-50">{{ __('projects.invoice.payment_review') }}</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $invoice->invoice_number }} · {{ \App\Support\Money::of($payment->amount, $invoice->currency)->format() }} · {{ $payment->method }}</p>
                                @if ($payment->reference_number)<p class="text-xs text-slate-600 dark:text-slate-400">{{ __('projects.invoice.reference') }}: {{ $payment->reference_number }}</p>@endif
                                @if ($payment->bank_name || $payment->sender_name)<p class="text-xs text-slate-600 dark:text-slate-400">{{ __('payments.admin.bank') }}: {{ $payment->bank_name ?: '-' }} · {{ __('payments.admin.sender') }}: {{ $payment->sender_name ?: '-' }}</p>@endif
                                @if ($payment->paid_at)<p class="text-xs text-slate-600 dark:text-slate-400">{{ __('payments.admin.paid_at') }}: {{ $payment->paid_at->translatedFormat('d M Y') }}</p>@endif
                                @if ($payment->proof_path)<a class="mt-1 inline-block text-xs font-medium text-growth hover:underline" href="{{ route('admin.payments.proof', ['payment' => $payment->id]) }}">{{ __('projects.invoice.view_proof') }}</a>@endif
                            </div>
                            <flux:button size="sm" variant="primary" wire:click="verifyPayment({{ $payment->id }})" wire:confirm="{{ __('projects.invoice.confirm_verify') }}">{{ __('projects.invoice.verify') }}</flux:button>
                        </div>
                        <form wire:submit="rejectPayment({{ $payment->id }})" class="mt-4 flex flex-col gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <flux:textarea wire:model="paymentRejectionReasons.{{ $payment->id }}" :label="__('projects.invoice.rejection_reason')" rows="2" />
                            <flux:error :name="'paymentRejectionReasons.'.$payment->id" />
                            <div class="flex justify-end"><flux:button type="submit" size="sm" variant="danger">{{ __('projects.invoice.reject_payment') }}</flux:button></div>
                        </form>
                    </section>
                @endforeach
            @endforeach
        </x-card>
    </div>
</div>
