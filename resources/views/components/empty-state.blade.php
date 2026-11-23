@props(['title' => null, 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center gap-2 px-6 py-12 text-center') }}>
    <svg class="size-10 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="22 12 16 12 14 15 10 15 8 12 2 12" />
        <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
    </svg>

    <h3 class="font-heading text-base font-semibold text-slate-900 dark:text-slate-50">{{ $title ?? __('ui.empty_title') }}</h3>
    <p class="max-w-sm text-sm text-slate-600 dark:text-slate-400">{{ $description ?? __('ui.empty_description') }}</p>

    @if ($slot->isNotEmpty())
        <div class="mt-3">{{ $slot }}</div>
    @endif
</div>
