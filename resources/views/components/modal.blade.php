@props(['name', 'title' => null, 'maxWidth' => 'lg'])

@php
    $width = [
        'sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl',
    ][$maxWidth] ?? 'sm:max-w-lg';
    $titleId = 'modal-title-'.$name;
@endphp

{{-- Buka: $dispatch('open-modal', 'nama')   Tutup: $dispatch('close-modal', 'nama') --}}
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === @js($name)) open = true"
     x-on:close-modal.window="if ($event.detail === @js($name)) open = false"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak
     class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
     role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-950/70" x-on:click="open = false"></div>

    <div x-show="open" x-transition x-trap.noscroll="open"
         {{ $attributes->class(['relative w-full rounded-t-card bg-white shadow-xl dark:bg-slate-900 sm:rounded-card', $width]) }}>
        <header class="flex items-center justify-between gap-3 border-b border-slate-200 px-card py-3 dark:border-slate-800">
            <h2 id="{{ $titleId }}" class="font-heading text-lg font-semibold text-slate-900 dark:text-slate-50">{{ $title }}</h2>
            <button type="button" x-on:click="open = false" aria-label="{{ __('ui.close') }}"
                    class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100">
                <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
            </button>
        </header>

        <div class="p-card text-sm text-slate-700 dark:text-slate-300">{{ $slot }}</div>

        @isset($footer)
            <footer class="flex flex-wrap justify-end gap-2 border-t border-slate-200 px-card py-3 dark:border-slate-800">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
