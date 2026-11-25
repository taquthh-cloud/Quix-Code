<x-layouts::portal :title="__('portal.title')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('portal.title') }}</flux:heading>
            <flux:text>{{ __('portal.greeting', ['name' => auth()->user()->name]) }}</flux:text>
        </div>

        @forelse ($projects as $project)
            <x-card wire:key="project-{{ $project->id }}" data-project-card="{{ $project->id }}">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h2 class="min-w-0 break-words font-heading text-lg font-semibold text-slate-900 dark:text-slate-50">{{ $project->title }}</h2>
                        <x-badge-status :status="$project->status" />
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1"><x-progress-bar :value="$project->progress_percent" :show-value="false" /></div>
                        <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ $project->progress_percent }}%</span>
                    </div>

                    @if (isset($actions[$project->id][0]))
                        <x-next-action :action="$actions[$project->id][0]" compact />
                    @endif

                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                        <flux:button variant="primary" class="min-h-11 w-full sm:w-auto" :href="route('portal.projects.show', $project->id)" wire:navigate>
                            {{ __('portal.project_details') }}
                        </flux:button>
                        <flux:button class="min-h-11 w-full sm:w-auto" :href="route('portal.projects.negotiation', $project->id)" wire:navigate>
                            {{ __('portal.quotation_chat') }}
                            @if ($project->unread_chats_count > 0)
                                <flux:badge size="sm" color="emerald" class="ms-1.5" aria-label="{{ trans_choice('portal.unread_messages', $project->unread_chats_count, ['count' => $project->unread_chats_count]) }}">{{ $project->unread_chats_count }}</flux:badge>
                            @endif
                        </flux:button>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card>
                <x-empty-state :title="__('portal.no_projects')" :description="__('portal.no_projects_hint')" />
            </x-card>
        @endforelse
    </div>
</x-layouts::portal>
