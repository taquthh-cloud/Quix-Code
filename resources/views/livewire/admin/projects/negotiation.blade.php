<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('admin.projects.show', $project->id) }}" wire:navigate
               class="text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ $project->title }}</a>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('quotations.negotiation.title') }}</h1>
                <x-badge-status :status="$project->status" />
            </div>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ $project->client?->name }} · {{ $project->currency->label() }}</p>
        </div>

        @if ($canCreate)
            <flux:button variant="primary" icon="plus" :href="route('admin.quotations.create', $project->id)" wire:navigate>
                {{ __('quotations.negotiation.new') }}
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300" role="status">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-5">
        <div class="flex flex-col gap-6 xl:col-span-3">
            <x-card :title="__('quotations.negotiation.versions')">
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('quotations.version') }}</th>
                            <th>{{ __('projects.columns.status') }}</th>
                            <th class="text-end">{{ __('quotations.total') }}</th>
                            <th></th>
                        </tr>
                    </x-slot:head>

                    @forelse ($quotations as $quotation)
                        <tr wire:key="quotation-{{ $quotation->id }}">
                            <td class="font-medium text-slate-900 dark:text-slate-50">v{{ $quotation->version }}
                                <span class="block text-xs font-normal text-slate-600 dark:text-slate-400">{{ __('quotations.items_count', ['count' => $quotation->items_count]) }}</span>
                            </td>
                            <td><x-badge-status :status="$quotation->status" /></td>
                            <td class="text-end tabular-nums">{{ $quotation->totalMoney()->format() }}</td>
                            <td class="text-end">
                                <div class="flex flex-wrap justify-end gap-1.5">
                                    <flux:button size="sm" :href="route('admin.quotations.edit', [$project->id, $quotation->id])" wire:navigate>
                                        {{ $quotation->status === \App\Enums\QuotationStatus::Draft ? __('ui.edit') : __('quotations.view') }}
                                    </flux:button>
                                    @if ($quotations->contains(fn ($other) => $other->version < $quotation->version))
                                        <flux:button size="sm" variant="ghost" wire:click="toggleCompare('{{ $quotation->id }}')">
                                            {{ $comparisonCurrent?->id === $quotation->id ? __('quotations.compare.hide') : __('quotations.compare.button') }}
                                        </flux:button>
                                    @endif
                                    <flux:button size="sm" variant="ghost" icon="document-arrow-down" :href="route('quotations.print', $quotation->id)" target="_blank">{{ __('quotations.pdf') }}</flux:button>
                                    @if ($canCreate && $quotation->status !== \App\Enums\QuotationStatus::Draft)
                                        <flux:button size="sm" variant="ghost" wire:click="newVersion('{{ $quotation->id }}')">{{ __('quotations.builder.new_version') }}</flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-slate-600 dark:text-slate-400">{{ __('quotations.negotiation.none') }}</td>
                        </tr>
                    @endforelse
                </x-table>
            </x-card>

            @if ($comparison)
                <x-card>
                    <x-quotation-compare :comparison="$comparison" :currency="$project->currency"
                                         :old-version="$comparisonPrevious->version" :new-version="$comparisonCurrent->version" />
                </x-card>
            @endif

            <x-card :title="__('quotations.negotiation.invoices')">
                @can('create', [\App\Models\Invoice::class, $project])
                    <div class="mb-4 flex justify-end">
                        <flux:button size="sm" variant="primary" icon="plus" :href="route('admin.projects.invoices', $project->id)" wire:navigate>
                            {{ __('projects.invoice.create') }}
                        </flux:button>
                    </div>
                @endcan
                <x-invoice-list :invoices="$invoices" />
            </x-card>
        </div>

        <div class="xl:col-span-2">
            <livewire:chat.room :project-id="$project->id" :key="'chat-'.$project->id" />
        </div>
    </div>
</div>
