@props(['action', 'compact' => false])

@php
    $tone = [
        'action' => ['box' => 'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10', 'title' => 'text-amber-950 dark:text-amber-100', 'text' => 'text-amber-900 dark:text-amber-200', 'chip' => 'bg-amber-100 text-amber-900 ring-amber-300 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/40'],
        'waiting' => ['box' => 'border-sky-200 bg-sky-50 dark:border-sky-500/30 dark:bg-sky-500/10', 'title' => 'text-sky-950 dark:text-sky-100', 'text' => 'text-sky-900 dark:text-sky-200', 'chip' => 'bg-sky-50 text-sky-900 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-200 dark:ring-sky-500/30'],
        'info' => ['box' => 'border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/60', 'title' => 'text-slate-900 dark:text-slate-50', 'text' => 'text-slate-700 dark:text-slate-300', 'chip' => 'bg-slate-100 text-slate-800 ring-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600'],
    ][$action->tone];
@endphp

@if ($compact)
    @if ($action->url)
        <a href="{{ $action->url }}" wire:navigate data-next-action="{{ $action->key }}"
           class="inline-flex min-h-11 max-w-full items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset hover:underline {{ $tone['chip'] }}">
            <span class="break-words">{{ $action->title }}</span><span aria-hidden="true" class="ms-1">→</span>
        </a>
    @else
        <span data-next-action="{{ $action->key }}" class="inline-flex min-h-11 max-w-full items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset {{ $tone['chip'] }}">
            <span class="break-words">{{ $action->title }}</span>
        </span>
    @endif
@else
    <section data-next-action="{{ $action->key }}" aria-label="{{ __('next_action.heading') }}"
             class="flex flex-col gap-3 rounded-card border p-card sm:flex-row sm:items-center sm:justify-between {{ $tone['box'] }}">
        <div class="min-w-0">
            <h2 class="font-heading text-base font-semibold {{ $tone['title'] }}">{{ $action->title }}</h2>
            <p class="mt-1 break-words text-sm {{ $tone['text'] }}">{{ $action->description }}</p>
        </div>
        @if ($action->url && $action->label)
            <flux:button :variant="$action->needsClient() ? 'primary' : 'filled'" class="min-h-11 shrink-0" :href="$action->url" wire:navigate>
                {{ $action->label }}
            </flux:button>
        @endif
    </section>
@endif
