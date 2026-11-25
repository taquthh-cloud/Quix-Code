<div class="flex flex-col gap-6">
    <div>
        <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.nav.activity') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.activity.subtitle') }}</p>
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <flux:select wire:model.live="projectId" :label="__('admin.activity.project_filter')">
            <flux:select.option value="">{{ __('admin.activity.all_projects') }}</flux:select.option>
            @foreach ($projects as $project)
                <flux:select.option :value="$project->id">{{ $project->title }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="userId" :label="__('admin.activity.user_filter')">
            <flux:select.option value="">{{ __('admin.activity.all_users') }}</flux:select.option>
            @foreach ($users as $user)
                <flux:select.option :value="(string) $user->id">{{ $user->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="action" :label="__('admin.activity.action_filter')">
            <flux:select.option value="">{{ __('admin.activity.all_actions') }}</flux:select.option>
            @foreach ($actions as $activityAction)
                <flux:select.option :value="$activityAction->value">{{ $activityAction->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-x-auto rounded-card border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase text-slate-600 dark:border-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">{{ __('admin.activity.when') }}</th>
                    <th class="px-4 py-3">{{ __('admin.activity.who') }}</th>
                    <th class="px-4 py-3">{{ __('admin.activity.action') }}</th>
                    <th class="px-4 py-3">{{ __('admin.activity.record') }}</th>
                    <th class="px-4 py-3">{{ __('admin.activity.details') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                @forelse ($logs as $log)
                    <tr wire:key="activity-{{ $log->id }}" class="align-top">
                        <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600 dark:text-slate-400">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? __('projects.show.system') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-50">{{ \App\Enums\ActivityAction::tryFrom($log->action)?->label() ?? $log->action }}</td>
                        <td class="px-4 py-3">
                            <span>{{ class_basename($log->subject_type) }}</span>
                            <span class="block max-w-40 truncate font-mono text-xs text-slate-600 dark:text-slate-400">{{ $log->subject_id }}</span>
                        </td>
                        <td class="max-w-md px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                            @if ($log->properties)
                                <pre class="whitespace-pre-wrap break-words font-sans">{{ json_encode($log->properties, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-600 dark:text-slate-400">{{ __('admin.activity.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
