@props(['label', 'value', 'hint' => null])

<x-card {{ $attributes }}>
    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $label }}</p>
    <p class="mt-2 font-heading text-3xl font-bold tabular-nums text-slate-900 dark:text-slate-50">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $hint }}</p>
    @endif
</x-card>
