<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <a href="{{ route('portal.overview') }}" wire:navigate class="inline-flex min-h-11 items-center text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ __('portal.title') }}</a>
        <div class="flex flex-wrap items-center gap-3">
            <flux:heading size="xl">{{ $project->title }}</flux:heading>
            <x-badge-status :status="$project->status" />
        </div>
        <flux:text>{{ __('quotations.negotiation.client_intro') }}</flux:text>
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="flex flex-col gap-6 xl:col-span-3">
            @forelse ($quotations as $quotation)
                <x-card wire:key="quotation-{{ $quotation->id }}" :title="__('quotations.title_version', ['version' => $quotation->version])">
                    <x-quotation-card :quotation="$quotation" />

                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        @if ($quotations->contains(fn ($other) => $other->version < $quotation->version))
                            <flux:button size="sm" variant="ghost" wire:click="toggleCompare('{{ $quotation->id }}')">
                                {{ $comparingId === $quotation->id ? __('quotations.compare.hide') : __('quotations.compare.button') }}
                            </flux:button>
                        @endif
                        <flux:button size="sm" variant="ghost" icon="document-arrow-down" :href="route('quotations.print', $quotation->id)" target="_blank">{{ __('quotations.pdf') }}</flux:button>
                    </div>

                    @if ($comparison && $comparingId === $quotation->id)
                        <x-quotation-compare class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-800"
                                             :comparison="$comparison" :currency="$quotation->currency"
                                             :old-version="$comparisonPrevious->version" :new-version="$quotation->version" />
                    @endif

                    @can('respond', $quotation)
                        <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <flux:button variant="ghost" wire:click="reject('{{ $quotation->id }}')" wire:confirm="{{ __('quotations.confirm_reject') }}">
                                {{ __('quotations.reject') }}
                            </flux:button>
                            @if ($confirmingId !== $quotation->id)
                                <flux:button variant="primary" icon="check" wire:click="startAccept('{{ $quotation->id }}')">
                                    {{ __('quotations.accept') }}
                                </flux:button>
                            @endif
                        </div>

                        @if ($confirmingId === $quotation->id)
                            <div class="mt-4 flex flex-col gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-500/40 dark:bg-amber-500/10">
                                <p class="font-medium text-slate-900 dark:text-slate-50">{{ __('quotations.confirm_accept') }}</p>
                                @if ($termsText !== '')
                                    <div class="max-h-40 overflow-y-auto whitespace-pre-line rounded border border-slate-200 bg-white p-3 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">{{ $termsText }}</div>
                                @endif
                                <flux:checkbox wire:model.live="agreed" :label="__('quotations.agree_statement')" />
                                <flux:error name="agreed" />
                                <div class="flex flex-wrap justify-end gap-2">
                                    <flux:button variant="ghost" wire:click="cancelAccept">{{ __('quotations.cancel') }}</flux:button>
                                    <flux:button variant="primary" icon="check" wire:click="accept('{{ $quotation->id }}')" :disabled="! $agreed">
                                        {{ __('quotations.accept_final') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    @endcan
                </x-card>
            @empty
                <x-card>
                    <x-empty-state :title="__('quotations.negotiation.client_none')" :description="__('quotations.negotiation.client_none_hint')" />
                </x-card>
            @endforelse

            @if ($invoices->isNotEmpty())
                <x-card :title="__('quotations.negotiation.invoices')">
                    <x-invoice-list :invoices="$invoices" />
                </x-card>
            @endif
        </div>

        <div class="xl:col-span-2">
            <livewire:chat.room :project-id="$project->id" :key="'chat-'.$project->id" />
        </div>
    </div>
</div>
