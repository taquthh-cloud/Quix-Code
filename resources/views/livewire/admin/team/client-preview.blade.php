<div class="flex flex-col gap-6">
    <section class="rounded-card border border-amber-300 bg-amber-50 p-4 dark:border-amber-500/40 dark:bg-amber-500/10" aria-labelledby="client-preview-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 id="client-preview-title" class="font-heading text-lg font-semibold text-amber-950 dark:text-amber-100">{{ __('admin.team.client_preview') }} · {{ $client->name }}</h1>
                <p class="mt-1 text-sm text-amber-900 dark:text-amber-200">{{ __('admin.team.preview_read_only') }}</p>
                <p class="mt-2 text-sm text-amber-900 dark:text-amber-200"><strong>{{ __('admin.team.preview_reason_label') }}:</strong> {{ $reason }}</p>
            </div>
            <flux:button variant="ghost" wire:click="endPreview">{{ __('admin.team.preview_end') }}</flux:button>
        </div>
    </section>

    <div>
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ $client->email }}</p>
        <h2 class="mt-1 font-heading text-xl font-semibold text-slate-900 dark:text-slate-50">{{ __('portal.project_details') }}</h2>
    </div>

    @forelse ($projects as $project)
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900 dark:text-slate-50">{{ $project->title }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $project->description }}</p>
                </div>
                <x-badge-status :status="$project->status" />
            </div>

            <div class="mt-4 flex flex-col gap-2">
                <div class="flex justify-between text-sm"><span>{{ __('ui.progress') }}</span><span class="tabular-nums">{{ $project->progress_percent }}%</span></div>
                <x-progress-bar :value="$project->progress_percent" />
            </div>

            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                <section aria-label="{{ __('portal.milestones') }}">
                    <h4 class="font-medium text-slate-900 dark:text-slate-50">{{ __('portal.milestones') }}</h4>
                    @forelse ($project->milestones as $milestone)
                        <div class="flex justify-between gap-3 border-b border-slate-200 py-2 text-sm last:border-0 dark:border-slate-800">
                            <span>{{ $milestone->title }}</span><span class="text-slate-600 dark:text-slate-400">{{ $milestone->status->label() }}</span>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('portal.no_milestones') }}</p>
                    @endforelse
                </section>

                <section aria-label="{{ __('projects.invoice.title') }}">
                    <h4 class="font-medium text-slate-900 dark:text-slate-50">{{ __('projects.invoice.title') }}</h4>
                    @forelse ($project->invoices as $invoice)
                        <div class="flex justify-between gap-3 border-b border-slate-200 py-2 text-sm last:border-0 dark:border-slate-800">
                            <span>{{ $invoice->invoice_number }} · <x-badge-status :status="$invoice->status" /></span>
                            <strong class="whitespace-nowrap tabular-nums">{{ $invoice->money()->format() }}</strong>
                        </div>
                    @empty
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('quotations.no_invoices') }}</p>
                    @endforelse
                </section>
            </div>
        </x-card>
    @empty
        <x-empty-state :title="__('admin.team.preview_empty_title')" :description="__('admin.team.preview_empty_hint')" />
    @endforelse
</div>
