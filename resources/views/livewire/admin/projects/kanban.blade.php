<div class="flex flex-col gap-6">
    <div>
        <h1 class="font-heading text-2xl font-bold text-slate-900 dark:text-slate-50">{{ __('admin.nav.kanban') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('admin.kanban.subtitle') }}</p>
    </div>

    <div class="-mx-4 overflow-x-auto px-4 pb-3 sm:mx-0 sm:px-0">
        <div class="flex min-w-max items-start gap-4">
            @foreach ($statuses as $status)
                <section class="w-80 rounded-card bg-slate-200/70 p-3 dark:bg-slate-900/70" aria-labelledby="ticket-column-{{ $status->value }}">
                    <header class="mb-3 flex items-center justify-between gap-2">
                        <h2 id="ticket-column-{{ $status->value }}" class="font-heading text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $status->label() }}</h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $columns[$status->value]->count() }}</span>
                    </header>

                    <div data-ticket-board data-status="{{ $status->value }}" class="flex min-h-24 flex-col gap-3" aria-label="{{ __('admin.kanban.column', ['status' => $status->label()]) }}">
                        @forelse ($columns[$status->value] as $ticket)
                            <article data-ticket-id="{{ $ticket->id }}" wire:key="ticket-{{ $ticket->id }}" class="rounded-card border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <div class="flex items-start justify-between gap-2">
                                    <a href="{{ route('admin.projects.show', $ticket->project_id) }}" wire:navigate class="font-medium text-slate-900 hover:text-growth hover:underline dark:text-slate-50">{{ $ticket->title }}</a>
                                    @can('updateStatus', $ticket)
                                        <button type="button" data-drag-handle class="cursor-grab rounded p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-growth dark:hover:bg-slate-800 dark:hover:text-white" aria-label="{{ __('admin.kanban.drag_ticket', ['ticket' => $ticket->title]) }}">
                                            <flux:icon name="bars-3" class="size-4" />
                                        </button>
                                    @endcan
                                </div>
                                <p class="mt-2 text-xs text-slate-600 dark:text-slate-400">{{ __('admin.kanban.project') }}: {{ $ticket->project?->title }}</p>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 dark:bg-slate-800">{{ $ticket->category->label() }}</span>
                                    <span>{{ $ticket->priority->label() }}</span>
                                    <span>· {{ __('admin.kanban.comments', ['count' => $ticket->comments_count]) }}</span>
                                </div>
                                @can('updateStatus', $ticket)
                                    <label class="sr-only" for="ticket-status-{{ $ticket->id }}">{{ __('admin.kanban.change_status', ['ticket' => $ticket->title]) }}</label>
                                    <select id="ticket-status-{{ $ticket->id }}" wire:change="setTicketStatus('{{ $ticket->id }}', $event.target.value)" class="mt-3 w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                        @foreach ($statuses as $ticketStatus)
                                            <option value="{{ $ticketStatus->value }}" @selected($ticket->status === $ticketStatus)>{{ $ticketStatus->label() }}</option>
                                        @endforeach
                                    </select>
                                @endcan
                            </article>
                        @empty
                            <p class="rounded-card border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400">{{ __('admin.kanban.empty') }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>
