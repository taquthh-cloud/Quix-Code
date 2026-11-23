<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-2">
        <a href="{{ route('portal.overview') }}" wire:navigate class="text-sm text-slate-600 hover:text-growth dark:text-slate-400">← {{ __('portal.title') }}</a>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl">{{ $project->title }}</flux:heading>
                <x-badge-status :status="$project->status" />
            </div>
            <flux:button size="sm" :href="route('portal.projects.negotiation', $project->id)" wire:navigate>
                {{ __('portal.quotation_chat') }}
            </flux:button>
        </div>
    </div>

    <x-card :title="__('portal.project_progress')">
        <div class="flex items-center gap-4">
            <div class="min-w-0 flex-1"><x-progress-bar :value="$project->progress_percent" /></div>
            <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ $project->progress_percent }}%</span>
        </div>
    </x-card>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-card :title="__('portal.milestones')">
            @if ($milestones->isEmpty())
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('portal.no_milestones') }}</p>
            @else
                <ol class="flex flex-col divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach ($milestones as $milestone)
                        <li wire:key="portal-milestone-{{ $milestone->id }}" class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-slate-50">{{ $milestone->title }}</p>
                            </div>
                            <span class="shrink-0 text-sm text-slate-600 dark:text-slate-400">{{ __('enums.milestone_status.'.$milestone->status->value) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>

        <x-card :title="__('portal.invoices')">
            <x-invoice-list :invoices="$invoices" />
        </x-card>
    </div>

    <x-card :title="__('portal.client_requests')">
        @if (session()->has('portal-request-created'))
            <div role="status" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ __('portal.request_created') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)]">
            <div>
                @forelse ($tickets as $ticket)
                    <article wire:key="portal-ticket-{{ $ticket->id }}" class="border-b border-slate-200 py-4 first:pt-0 last:border-0 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-medium text-slate-900 dark:text-slate-50">{{ $ticket->title }}</h3>
                            <x-badge-status :status="$ticket->status" />
                        </div>
                        <p class="mt-2 whitespace-pre-line text-sm text-slate-600 dark:text-slate-400">{{ $ticket->description }}</p>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('enums.ticket_category.'.$ticket->category->value) }} · {{ __('enums.ticket_priority.'.$ticket->priority->value) }}
                        </p>
                    </article>
                @empty
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('portal.no_requests') }}</p>
                @endforelse
            </div>

            <form wire:submit="submitRequest" class="flex flex-col gap-4 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <h3 class="font-medium text-slate-900 dark:text-slate-50">{{ __('portal.new_request') }}</h3>
                <flux:input wire:model="title" :label="__('portal.request_title')" required maxlength="120" />
                <flux:textarea wire:model="description" :label="__('portal.request_description')" required rows="4" maxlength="5000" />
                <flux:select wire:model="category" :label="__('portal.request_category')">
                    @foreach (\App\Enums\TicketCategory::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="priority" :label="__('portal.request_priority')">
                    @foreach (\App\Enums\TicketPriority::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submitRequest">
                        {{ __('portal.submit_request') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </x-card>
</div>
