<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('projects.title') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('projects.subtitle') }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('admin')
                <flux:button variant="ghost" :href="route('admin.exports', ['type' => 'projects', 'q' => $search, 'status' => $status])">{{ __('admin.search_page.export_projects') }}</flux:button>
            @endcan
            @can('create', \App\Models\Project::class)
                <flux:button variant="primary" icon="plus" :href="route('admin.projects.create')" wire:navigate>
                    {{ __('projects.new') }}
                </flux:button>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-card border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300" role="status">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-[1fr_16rem]">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('projects.search_placeholder')" :aria-label="__('ui.search')" />

        <flux:select wire:model.live="status" :aria-label="__('projects.columns.status')">
            <flux:select.option value="">{{ __('projects.all_statuses') }}</flux:select.option>
            @foreach ($statuses as $s)
                <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <x-table :empty="$projects->isEmpty()"
             :empty-title="$filtered ? __('projects.no_results') : __('projects.no_projects')"
             :empty-description="$filtered ? __('projects.no_results_hint') : __('projects.no_projects_hint')">
        <x-slot:head>
            <tr>
                <th>{{ __('projects.columns.project') }}</th>
                <th>{{ __('projects.columns.client') }}</th>
                <th>{{ __('projects.columns.status') }}</th>
                <th class="min-w-40">{{ __('projects.columns.progress') }}</th>
                @if (auth()->user()->isAdmin())
                    <th>{{ __('projects.columns.price') }}</th>
                @endif
                <th>{{ __('projects.columns.team') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($projects as $project)
            <tr wire:key="project-{{ $project->id }}">
                <td class="font-medium">
                    <a href="{{ route('admin.projects.show', $project->id) }}" wire:navigate
                       class="text-slate-900 hover:text-growth hover:underline dark:text-slate-50">{{ $project->title }}</a>
                </td>
                <td>{{ $project->client?->name }}</td>
                <td><x-badge-status :status="$project->status" /></td>
                <td><x-progress-bar :value="$project->progress_percent" size="sm" :show-value="false" /></td>
                @if (auth()->user()->isAdmin())
                    <td class="whitespace-nowrap tabular-nums">
                        @if ($project->final_price !== null)
                            {{ $project->finalPriceMoney()->format() }}
                        @elseif ($project->budget_reference !== null)
                            <span class="text-slate-600 dark:text-slate-400">{{ $project->budgetReferenceMoney()->format() }} {{ __('projects.reference_suffix') }}</span>
                        @else
                            —
                        @endif
                    </td>
                @endif
                <td class="tabular-nums">{{ $project->assignments_count }}</td>
            </tr>
        @endforeach
    </x-table>

    {{ $projects->links() }}
</div>
