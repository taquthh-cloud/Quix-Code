@props(['quotation', 'internal' => false])

{{-- Rincian penawaran. Klien tidak melihat operasional dan margin terpisah: keduanya digabung ($internal = false). --}}
@php
    $money = fn (int $amount) => \App\Support\Money::of($amount, $quotation->currency)->format();
    $overhead = $quotation->total - $quotation->subtotal;
@endphp

<div {{ $attributes->class('flex flex-col gap-4') }}>
    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
        <x-badge-status :status="$quotation->status" />
        @if ($quotation->valid_until)
            <span @class(['text-rose-700 dark:text-rose-300' => $quotation->isExpired()])>
                {{ __('quotations.valid_until') }} {{ $quotation->valid_until->translatedFormat('d M Y') }}
                @if ($quotation->isExpired()) · {{ __('quotations.expired') }} @endif
            </span>
        @endif
        @if ($quotation->accepted_at)
            <span>{{ __('quotations.accepted_at') }} {{ $quotation->accepted_at->translatedFormat('d M Y H:i') }}</span>
        @endif
    </div>

    <x-table>
        <x-slot:head>
            <tr>
                <th>{{ __('quotations.builder.item_title') }}</th>
                <th class="text-end">{{ __('quotations.builder.hours') }}</th>
                <th class="text-end">{{ __('quotations.builder.rate') }}</th>
                <th class="text-end">{{ __('quotations.builder.amount') }}</th>
            </tr>
        </x-slot:head>

        @foreach ($quotation->items as $item)
            <tr wire:key="item-{{ $quotation->id }}-{{ $item->id }}">
                <td>
                    <span class="font-medium text-slate-900 dark:text-slate-50">{{ $item->title }}</span>
                    @if ($item->description)
                        <span class="block whitespace-pre-line text-xs text-slate-600 dark:text-slate-400">{{ $item->description }}</span>
                    @endif
                </td>
                <td class="text-end tabular-nums">{{ rtrim(rtrim((string) $item->hours, '0'), '.') ?: '0' }}</td>
                <td class="text-end tabular-nums">{{ $money($item->rate) }}</td>
                <td class="text-end tabular-nums">{{ $money($item->amount) }}</td>
            </tr>
        @endforeach
    </x-table>

    <dl class="ms-auto grid w-full max-w-sm grid-cols-[1fr_auto] gap-x-6 gap-y-1 text-sm">
        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.subtotal') }}</dt>
        <dd class="text-end tabular-nums">{{ $money($quotation->subtotal) }}</dd>

        @if ($internal)
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.operational') }}</dt>
            <dd class="text-end tabular-nums">{{ $money($quotation->operational_cost) }}</dd>
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.margin') }} ({{ rtrim(rtrim((string) $quotation->margin_percent, '0'), '.') ?: '0' }}%)</dt>
            <dd class="text-end tabular-nums">{{ $money($quotation->marginMoney()->amount) }}</dd>
        @elseif ($overhead > 0)
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.overhead') }}</dt>
            <dd class="text-end tabular-nums">{{ $money($overhead) }}</dd>
        @endif

        <dt class="border-t border-slate-200 pt-2 font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-50">{{ __('quotations.total') }}</dt>
        <dd class="border-t border-slate-200 pt-2 text-end text-base font-semibold tabular-nums text-slate-900 dark:border-slate-800 dark:text-slate-50">{{ $money($quotation->total) }}</dd>

        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.dp_due', ['percent' => $quotation->dp_percent]) }}</dt>
        <dd class="text-end tabular-nums">{{ $quotation->dpMoney()->format() }}</dd>
    </dl>
</div>
