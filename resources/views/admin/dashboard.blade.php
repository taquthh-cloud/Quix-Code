<x-layouts::admin :title="__('admin.nav.command_center')">
    <div class="flex flex-col gap-6">
        <div>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.nav.command_center') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.dashboard.greeting', ['name' => auth()->user()->name]) }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :label="__('admin.dashboard.leads')" :value="$stats['leads']" />
            <x-stat-card :label="__('admin.dashboard.active')" :value="$stats['active']" />
            <x-stat-card :label="__('admin.dashboard.open_tickets')" :value="$stats['open_tickets']" />

            @if ($stats['unpaid'] !== null)
                <x-stat-card :label="__('admin.dashboard.unpaid')"
                             :value="$stats['unpaid'] === [] ? '—' : implode(' · ', $stats['unpaid'])"
                             :hint="__('admin.dashboard.awaiting_verification', ['count' => $stats['awaiting_verification']])" />
            @endif
        </div>

        @if ($stats['aging'] !== null)
            <section aria-labelledby="receivables-aging-title" class="flex flex-col gap-3">
                <h2 id="receivables-aging-title" class="font-heading text-lg font-semibold text-slate-900 dark:text-slate-50">{{ __('admin.dashboard.aging_title') }}</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['0_7', '8_30', 'over_30'] as $bucket)
                        <x-card :title="__('admin.dashboard.aging_'.$bucket)">
                            @forelse ($stats['aging'][$bucket] as $row)
                                <div class="flex items-center justify-between gap-3 py-1">
                                    <span class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.dashboard.aging_count', ['count' => $row['count']]) }} · {{ $row['currency'] }}</span>
                                    <strong class="text-right tabular-nums text-slate-900 dark:text-slate-50">{{ $row['amount'] }}</strong>
                                </div>
                            @empty
                                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.dashboard.aging_empty') }}</p>
                            @endforelse
                        </x-card>
                    @endforeach
                </div>
            </section>
        @endif

        <div>
            <h2 class="mb-3 font-heading text-lg font-semibold text-slate-900 dark:text-slate-50">{{ __('admin.dashboard.recent_projects') }}</h2>

            <x-table :empty="$recentProjects->isEmpty()" :empty-title="__('admin.dashboard.no_projects')">
                <x-slot:head>
                    <tr>
                        <th>{{ __('admin.dashboard.project') }}</th>
                        <th>{{ __('admin.dashboard.client') }}</th>
                        <th>{{ __('admin.dashboard.status') }}</th>
                        <th class="min-w-40">{{ __('ui.progress') }}</th>
                    </tr>
                </x-slot:head>

                @foreach ($recentProjects as $project)
                    <tr>
                        <td class="font-medium">{{ $project->title }}</td>
                        <td>{{ $project->client?->name }}</td>
                        <td><x-badge-status :status="$project->status" /></td>
                        <td><x-progress-bar :value="$project->progress_percent" size="sm" :show-value="false" /></td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    </div>
</x-layouts::admin>
