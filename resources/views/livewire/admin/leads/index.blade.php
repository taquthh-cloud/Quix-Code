<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('quotations.leads.title') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('quotations.leads.subtitle') }}</p>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('admin.projects.create')" wire:navigate>{{ __('quotations.leads.add') }}</flux:button>
    </div>

    <div class="max-w-sm">
        <flux:select wire:model.live="status" :label="__('quotations.leads.filter_status')">
            <flux:select.option value="">{{ __('quotations.leads.all_statuses') }}</flux:select.option>
            @foreach ($statuses as $pipelineStatus)
                <flux:select.option :value="$pipelineStatus->value">{{ $pipelineStatus->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-table :empty="$leads->isEmpty()" :empty-title="__('quotations.leads.none')" :empty-description="__('quotations.leads.none_hint')">
        <x-slot:head>
            <tr>
                <th>{{ __('projects.columns.project') }}</th>
                <th>{{ __('projects.columns.client') }}</th>
                <th>{{ __('projects.columns.status') }}</th>
                <th>{{ __('quotations.leads.source') }}</th>
                <th>{{ __('quotations.leads.latest') }}</th>
                <th class="text-end">{{ __('quotations.leads.unread') }}</th>
                <th></th>
            </tr>
        </x-slot:head>

        @foreach ($leads as $lead)
            <tr wire:key="lead-{{ $lead->id }}">
                <td class="font-medium text-slate-900 dark:text-slate-50">{{ $lead->title }}</td>
                <td>{{ $lead->client?->name }}</td>
                <td><x-badge-status :status="$lead->status" /></td>
                <td>{{ $lead->lead_source ? __('projects.lead_sources.'.$lead->lead_source) : '—' }}</td>
                <td>
                    @if ($lead->latestQuotation)
                        <span class="tabular-nums">v{{ $lead->latestQuotation->version }} · {{ $lead->latestQuotation->totalMoney()->format() }}</span>
                        <x-badge-status class="ms-1" :status="$lead->latestQuotation->status" />
                    @else
                        <span class="text-slate-600 dark:text-slate-400">{{ __('quotations.leads.no_quotation') }}</span>
                    @endif
                </td>
                <td class="text-end tabular-nums">{{ $lead->unread_chats_count }}</td>
                <td class="text-end">
                    <flux:button size="sm" :href="route('admin.projects.negotiation', $lead->id)" wire:navigate>{{ __('quotations.open') }}</flux:button>
                </td>
            </tr>
        @endforeach
    </x-table>

    {{ $leads->links() }}
</div>
