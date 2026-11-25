<x-layouts::app :title="__('portal.title')">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('portal.title') }}</flux:heading>
            <flux:text>{{ __('portal.greeting', ['name' => auth()->user()->name]) }}</flux:text>
        </div>

        <x-table :empty="$projects->isEmpty()" :empty-title="__('portal.no_projects')" :empty-description="__('portal.no_projects_hint')">
            <x-slot:head>
                <tr>
                    <th>{{ __('projects.columns.project') }}</th>
                    <th>{{ __('projects.columns.status') }}</th>
                    <th class="min-w-40">{{ __('projects.columns.progress') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>

            @foreach ($projects as $project)
                <tr wire:key="project-{{ $project->id }}">
                    <td class="font-medium text-slate-900 dark:text-slate-50">{{ $project->title }}</td>
                    <td><x-badge-status :status="$project->status" /></td>
                    <td><x-progress-bar :value="$project->progress_percent" /></td>
                    <td class="text-end">
                        <div class="flex flex-wrap justify-end gap-2">
                            <flux:button size="sm" variant="ghost" :href="route('portal.projects.show', $project->id)" wire:navigate>
                                {{ __('portal.project_details') }}
                            </flux:button>
                            <flux:button size="sm" :href="route('portal.projects.negotiation', $project->id)" wire:navigate>
                                {{ __('portal.quotation_chat') }}
                                @if ($project->unread_chats_count > 0)
                                    <flux:badge size="sm" color="emerald" class="ms-1.5" aria-label="{{ trans_choice('portal.unread_messages', $project->unread_chats_count, ['count' => $project->unread_chats_count]) }}">{{ $project->unread_chats_count }}</flux:badge>
                                @endif
                            </flux:button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-layouts::app>
