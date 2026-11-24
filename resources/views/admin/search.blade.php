<x-layouts::admin :title="__('admin.search_page.title')">
    <main class="flex flex-col gap-6">
        <header>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.search_page.title') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.search_page.subtitle') }}</p>
        </header>

        <form method="GET" action="{{ route('admin.search') }}" class="grid gap-3 sm:grid-cols-[1fr_13rem_auto]">
            <flux:input name="q" value="{{ $term }}" :label="__('admin.search_page.query')" autocomplete="off" />
            <div class="flex flex-col gap-1.5">
                <label for="search-type" class="text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('admin.search_page.category') }}</label>
                <select id="search-type" name="type" class="min-h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    @foreach (['all', 'projects', 'invoices', 'clients'] as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ __('admin.search_page.types.'.$option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end"><flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('ui.search') }}</flux:button></div>
        </form>

        @if ($term === '')
            <x-empty-state :title="__('admin.search_page.empty_title')" :description="__('admin.search_page.empty_hint')" />
        @else
            <div class="flex flex-wrap justify-end gap-2">
                @foreach (['projects', 'invoices', 'clients'] as $exportType)
                    @if ($type === 'all' || $type === $exportType)
                        <flux:button size="sm" variant="ghost" :href="route('admin.exports', ['type' => $exportType, 'q' => $term])">{{ __('admin.search_page.export_'.$exportType) }}</flux:button>
                    @endif
                @endforeach
            </div>

            @if (in_array($type, ['all', 'projects'], true))
                <x-card :title="__('admin.search_page.types.projects')">
                    @forelse ($projects as $project)
                        <a class="flex items-center justify-between gap-3 border-b border-slate-200 py-3 last:border-0 dark:border-slate-800" href="{{ route('admin.projects.show', $project->id) }}" wire:navigate>
                            <span><strong class="block text-slate-900 dark:text-slate-50">{{ $project->title }}</strong><span class="text-sm text-slate-600 dark:text-slate-400">{{ $project->client?->name }}</span></span>
                            <x-badge-status :status="$project->status" />
                        </a>
                    @empty
                        <p class="py-3 text-sm text-slate-600 dark:text-slate-400">{{ __('admin.search_page.no_results') }}</p>
                    @endforelse
                </x-card>
            @endif

            @if (in_array($type, ['all', 'invoices'], true))
                <x-card :title="__('admin.search_page.types.invoices')">
                    @forelse ($invoices as $invoice)
                        <a class="flex items-center justify-between gap-3 border-b border-slate-200 py-3 last:border-0 dark:border-slate-800" href="{{ route('admin.projects.invoices', $invoice->project_id) }}" wire:navigate>
                            <span><strong class="block text-slate-900 dark:text-slate-50">{{ $invoice->invoice_number }}</strong><span class="text-sm text-slate-600 dark:text-slate-400">{{ $invoice->project->title }} · {{ $invoice->project->client?->name }}</span></span>
                            <span class="text-right"><strong class="block tabular-nums text-slate-900 dark:text-slate-50">{{ $invoice->money()->format() }}</strong><x-badge-status :status="$invoice->status" /></span>
                        </a>
                    @empty
                        <p class="py-3 text-sm text-slate-600 dark:text-slate-400">{{ __('admin.search_page.no_results') }}</p>
                    @endforelse
                </x-card>
            @endif

            @if (in_array($type, ['all', 'clients'], true))
                <x-card :title="__('admin.search_page.types.clients')">
                    @forelse ($clients as $client)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 py-3 last:border-0 dark:border-slate-800">
                            <span><strong class="block text-slate-900 dark:text-slate-50">{{ $client->name }}</strong><span class="text-sm text-slate-600 dark:text-slate-400">{{ $client->email }}@if ($client->whatsapp) · {{ $client->whatsapp }}@endif</span></span>
                            <flux:badge>{{ __('admin.search_page.types.clients') }}</flux:badge>
                        </div>
                    @empty
                        <p class="py-3 text-sm text-slate-600 dark:text-slate-400">{{ __('admin.search_page.no_results') }}</p>
                    @endforelse
                </x-card>
            @endif
        @endif
    </main>
</x-layouts::admin>
