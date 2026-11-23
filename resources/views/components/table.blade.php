@props(['empty' => false, 'emptyTitle' => null, 'emptyDescription' => null, 'loadingTarget' => null])

{{-- Pakai <x-slot:head> untuk baris <tr><th>…; isi default adalah <tr> di dalam <tbody>. --}}
<div {{ $attributes->class('relative overflow-hidden rounded-card border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900') }}>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-slate-800 dark:text-slate-200">
            @isset($head)
                <thead class="bg-slate-50 text-start text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-900 dark:text-slate-400 [&_th]:px-4 [&_th]:py-3 [&_th]:text-start">
                    {{ $head }}
                </thead>
            @endisset

            @unless ($empty)
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 [&_td]:px-4 [&_td]:py-3">
                    {{ $slot }}
                </tbody>
            @endunless
        </table>
    </div>

    @if ($empty)
        <x-empty-state :title="$emptyTitle" :description="$emptyDescription" />
    @endif

    {{-- Indikator memuat (hanya aktif di komponen Livewire) --}}
    <div wire:loading.flex @if ($loadingTarget) wire:target="{{ $loadingTarget }}" @endif
         class="absolute inset-0 hidden items-center justify-center bg-white/70 dark:bg-slate-950/60" role="status">
        <svg class="size-6 animate-spin text-growth" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
        </svg>
        <span class="sr-only">{{ __('ui.loading') }}</span>
    </div>
</div>
