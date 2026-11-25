@props(['comparison', 'currency', 'oldVersion', 'newVersion'])

{{-- Perbandingan dua versi quotation (T3-02). Data dari App\Support\QuotationComparison. --}}
@php
    $fmt = fn (int|string|null $value, string $type) => $value === null
        ? '-'
        : ($type === 'money' ? \App\Support\Money::of((int) $value, $currency)->format() : ((string) $value !== '' ? (string) $value : '-'));

    $badge = [
        'added' => 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        'removed' => 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
        'changed' => 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        'same' => 'bg-slate-100 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600',
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-4') }} data-testid="quotation-compare">
    <p class="text-sm font-medium text-slate-900 dark:text-slate-50">
        {{ __('quotations.compare.heading', ['old' => $oldVersion, 'new' => $newVersion]) }}
    </p>

    @unless ($comparison['changed'])
        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('quotations.compare.no_changes') }}</p>
    @endunless

    <x-table>
        <x-slot:head>
            <tr>
                <th></th>
                <th class="text-end">v{{ $oldVersion }}</th>
                <th class="text-end">v{{ $newVersion }}</th>
                <th class="text-end">{{ __('quotations.compare.difference') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($comparison['rows'] as $row)
            <tr @class(['font-medium' => $row['changed']])>
                <td>{{ __($row['label']) }}</td>
                <td class="text-end tabular-nums">{{ $fmt($row['old'], $row['type']) }}</td>
                <td class="text-end tabular-nums">{{ $fmt($row['new'], $row['type']) }}</td>
                <td class="text-end tabular-nums">
                    @if ($row['changed'] && $row['delta'] !== null)
                        {{ $row['delta'] > 0 ? '+' : '−' }} {{ \App\Support\Money::of(abs($row['delta']), $currency)->format() }}
                    @else
                        -
                    @endif
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('quotations.builder.items') }}</th>
                <th></th>
                <th class="text-end">v{{ $oldVersion }}</th>
                <th class="text-end">v{{ $newVersion }}</th>
            </tr>
        </x-slot:head>

        @foreach ($comparison['items'] as $item)
            <tr>
                <td>{{ $item['title'] }}</td>
                <td>
                    <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badge[$item['status']] }}">
                        {{ __('quotations.compare.item_'.$item['status']) }}
                    </span>
                </td>
                <td class="text-end tabular-nums">{{ $fmt($item['old'], 'money') }}</td>
                <td class="text-end tabular-nums">{{ $fmt($item['new'], 'money') }}</td>
            </tr>
        @endforeach
    </x-table>
</div>
