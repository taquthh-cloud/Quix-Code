<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <a href="{{ route('portal.overview') }}" wire:navigate class="text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ __('portal.title') }}</a>
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

                    @can('respond', $quotation)
                        <div class="mt-5 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800">
                            <flux:button variant="ghost" wire:click="reject('{{ $quotation->id }}')" wire:confirm="{{ __('quotations.confirm_reject') }}">
                                {{ __('quotations.reject') }}
                            </flux:button>
                            <flux:button variant="primary" icon="check" wire:click="accept('{{ $quotation->id }}')" wire:confirm="{{ __('quotations.confirm_accept') }}">
                                {{ __('quotations.accept') }}
                            </flux:button>
                        </div>
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
