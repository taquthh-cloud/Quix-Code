<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('admin.projects.negotiation', $project->id) }}" wire:navigate
               class="text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ __('quotations.negotiation.title') }}</a>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">
                    {{ __('quotations.builder.title') }}
                    @if ($quotation) · v{{ $quotation->version }} @endif
                </h1>
                @if ($quotation) <x-badge-status :status="$quotation->status" /> @endif
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $project->title }} · {{ $project->client?->name }} · {{ $project->currency->label() }}
            </p>
        </div>

        @if (! $editable)
            <flux:button variant="primary" icon="document-duplicate" wire:click="newVersion">{{ __('quotations.builder.new_version') }}</flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300" role="status">
            {{ session('status') }}
        </div>
    @endif

    @if (! $editable)
        <x-card :title="__('quotations.builder.readonly')">
            <x-quotation-card :quotation="$quotation->loadMissing('items')" :internal="true" />
        </x-card>
    @else
        <form wire:submit="save" class="flex flex-col gap-6">
            <x-card :title="__('quotations.builder.items')">
                <div class="flex flex-col gap-5">
                    @foreach ($items as $i => $item)
                        <div wire:key="item-row-{{ $i }}" class="grid gap-3 border-b border-slate-200 pb-5 last:border-0 last:pb-0 dark:border-slate-800 sm:grid-cols-6">
                            <flux:input wire:model="items.{{ $i }}.title" :label="__('quotations.builder.item_title')" class="sm:col-span-6" />
                            <flux:textarea wire:model="items.{{ $i }}.description" rows="2" :label="__('quotations.builder.item_description')" class="sm:col-span-6" />

                            <flux:input wire:model.live.debounce.500ms="items.{{ $i }}.hours" inputmode="decimal" :label="__('quotations.builder.hours')" class="sm:col-span-2" />
                            <flux:input wire:model.live.debounce.500ms="items.{{ $i }}.rate" inputmode="decimal" :label="__('quotations.builder.rate')" class="sm:col-span-2" />

                            <div class="sm:col-span-2">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('quotations.builder.amount') }}</span>
                                <p class="mt-2 text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-50">
                                    {{ \App\Support\Money::of($totals['amounts'][$i] ?? 0, $project->currency)->format() }}
                                </p>
                            </div>

                            <div class="sm:col-span-6">
                                <flux:error name="items.{{ $i }}.title" />
                                <flux:error name="items.{{ $i }}.description" />
                                <flux:error name="items.{{ $i }}.hours" />
                                <flux:error name="items.{{ $i }}.rate" />
                            </div>

                            <div class="flex justify-end gap-1.5 sm:col-span-6">
                                <flux:button size="sm" variant="ghost" icon="chevron-up" :aria-label="__('projects.show.ms_up')"
                                             wire:click="moveItem({{ $i }}, 'up')" :disabled="$loop->first" />
                                <flux:button size="sm" variant="ghost" icon="chevron-down" :aria-label="__('projects.show.ms_down')"
                                             wire:click="moveItem({{ $i }}, 'down')" :disabled="$loop->last" />
                                <flux:button size="sm" variant="ghost" icon="trash" :aria-label="__('ui.delete')"
                                             wire:click="removeItem({{ $i }})" :disabled="count($items) === 1" />
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:error name="items" class="mt-3" />

                <div class="mt-5">
                    <flux:button icon="plus" wire:click="addItem">{{ __('quotations.builder.add_item') }}</flux:button>
                </div>
            </x-card>

            <div class="grid gap-6 lg:grid-cols-2">
                <x-card :title="__('quotations.builder.terms')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model.live.debounce.500ms="operational_cost" inputmode="decimal" :label="__('quotations.builder.operational')" />
                        <flux:input wire:model.live.debounce.500ms="margin_percent" inputmode="decimal" :label="__('quotations.builder.margin')" />
                        <flux:input wire:model.live.debounce.500ms="discount" inputmode="decimal" :label="__('quotations.builder.discount')" />
                        <flux:checkbox wire:model.live="apply_tax" :label="__('quotations.builder.apply_tax', ['rate' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($this->taxRateBp()), '0'), '.') ?: '0'])" class="self-end" />

                        <flux:select wire:model.live="dp_percent" :label="__('quotations.builder.dp')">
                            @foreach ($dpOptions as $option)
                                <flux:select.option :value="(string) $option">{{ __('quotations.builder.dp_option_'.$option) }}</flux:select.option>
                            @endforeach
                            <flux:select.option value="custom">{{ __('quotations.builder.dp_option_custom') }}</flux:select.option>
                        </flux:select>
                        @if ($dp_percent === 'custom')
                            <flux:input wire:model.live.debounce.500ms="custom_terms" inputmode="decimal" :label="__('quotations.builder.custom_terms')" placeholder="25, 25, 50" />
                        @endif
                        <flux:input wire:model="valid_until" type="date" :label="__('quotations.builder.valid_until')" />
                    </div>
                    <div class="mt-2 flex flex-col gap-1">
                        <flux:error name="operational_cost" />
                        <flux:error name="margin_percent" />
                        <flux:error name="discount" />
                        <flux:error name="dp_percent" />
                        <flux:error name="custom_terms" />
                        <flux:error name="valid_until" />
                    </div>
                </x-card>

                <x-card :title="__('quotations.builder.summary')">
                    <dl class="grid grid-cols-[1fr_auto] gap-x-6 gap-y-2 text-sm">
                        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.subtotal') }}</dt>
                        <dd class="text-end tabular-nums">{{ \App\Support\Money::of($totals['subtotal'], $project->currency)->format() }}</dd>
                        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.operational') }}</dt>
                        <dd class="text-end tabular-nums">{{ \App\Support\Money::of($totals['operational'], $project->currency)->format() }}</dd>
                        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.margin') }}</dt>
                        <dd class="text-end tabular-nums">{{ \App\Support\Money::of($totals['margin'], $project->currency)->format() }}</dd>
                        @if ($totals['discount'] > 0)
                            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.discount') }}</dt>
                            <dd class="text-end tabular-nums">&minus; {{ \App\Support\Money::of($totals['discount'], $project->currency)->format() }}</dd>
                        @endif
                        @if ($totals['tax_bp'] > 0)
                            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.tax', ['rate' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($totals['tax_bp']), '0'), '.') ?: '0']) }}</dt>
                            <dd class="text-end tabular-nums">{{ \App\Support\Money::of($totals['tax'], $project->currency)->format() }}</dd>
                        @endif
                        <dt class="border-t border-slate-200 pt-2 font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-50">{{ __('quotations.total') }}</dt>
                        <dd class="border-t border-slate-200 pt-2 text-end text-lg font-semibold tabular-nums text-slate-900 dark:border-slate-800 dark:text-slate-50">{{ \App\Support\Money::of($totals['total'], $project->currency)->format() }}</dd>
                        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.dp_amount') }}</dt>
                        <dd class="text-end tabular-nums">{{ \App\Support\Money::of($totals['dp'], $project->currency)->format() }}</dd>
                        @foreach (array_slice($totals['terms'], 1) as $n => $term)
                            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.term_n', ['n' => $n + 2, 'percent' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($term['bp']), '0'), '.') ?: '0']) }}</dt>
                            <dd class="text-end tabular-nums">{{ \App\Support\Money::of($term['amount'], $project->currency)->format() }}</dd>
                        @endforeach
                    </dl>
                    <p class="mt-4 text-xs text-slate-600 dark:text-slate-400">{{ __('quotations.builder.client_hint') }}</p>
                </x-card>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <flux:button type="submit" icon="check">{{ __('quotations.builder.save_draft') }}</flux:button>
                <flux:button variant="primary" icon="paper-airplane" wire:click="saveAndSend" wire:confirm="{{ __('quotations.builder.confirm_send') }}">
                    {{ __('quotations.builder.save_send') }}
                </flux:button>
            </div>
        </form>
    @endif
</div>
