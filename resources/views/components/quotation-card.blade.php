@props(['quotation', 'internal' => false])

{{-- Rincian penawaran. Klien tidak melihat operasional dan margin terpisah: keduanya digabung ($internal = false). --}}
@php
    $money = fn (int $amount) => \App\Support\Money::of($amount, $quotation->currency)->format();
    // Klien melihat operasional + margin digabung (tanpa diskon/PPN, yang ditampilkan terpisah).
    $overhead = $quotation->operational_cost + $quotation->marginMoney()->amount;
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

    @if ($quotation->isExpiringSoon())
        @php($daysLeft = $quotation->daysLeft())
        <p class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200" role="status" data-testid="expiry-notice">
            {{ $daysLeft === 0 ? __('quotations.expires_today') : trans_choice('quotations.expires_in', $daysLeft, ['count' => $daysLeft]) }}
        </p>
    @elseif (! $internal && $quotation->status === \App\Enums\QuotationStatus::Superseded)
        <p class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-800/50 dark:text-slate-300" data-testid="superseded-notice">
            {{ __('quotations.superseded_hint') }}
        </p>
    @elseif (! $internal && $quotation->status === \App\Enums\QuotationStatus::Expired)
        <p class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200" data-testid="expired-notice">
            {{ __('quotations.expired_hint') }}
        </p>
    @endif

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

        @if ($quotation->discount_amount > 0)
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.builder.discount') }}</dt>
            <dd class="text-end tabular-nums">&minus; {{ $money($quotation->discount_amount) }}</dd>
        @endif
        @if ($quotation->tax_bp > 0)
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.tax', ['rate' => $quotation->taxPercentLabel()]) }}</dt>
            <dd class="text-end tabular-nums">{{ $money($quotation->tax_amount) }}</dd>
        @endif

        <dt class="border-t border-slate-200 pt-2 font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-50">{{ __('quotations.total') }}</dt>
        <dd class="border-t border-slate-200 pt-2 text-end text-base font-semibold tabular-nums text-slate-900 dark:border-slate-800 dark:text-slate-50">{{ $money($quotation->total) }}</dd>

        @php($terms = $quotation->termMoney())
        @php($bps = $quotation->termBps())
        <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.dp_due', ['percent' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($bps[0]), '0'), '.') ?: '0']) }}</dt>
        <dd class="text-end tabular-nums">{{ $terms[0]->format() }}</dd>
        @foreach (array_slice($terms, 1, null, true) as $i => $term)
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.term_n', ['n' => $i + 1, 'percent' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($bps[$i]), '0'), '.') ?: '0']) }}</dt>
            <dd class="text-end tabular-nums">{{ $term->format() }}</dd>
        @endforeach
    </dl>

    @if ($internal && $quotation->accepted_at)
        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-lg border border-slate-200 p-3 text-xs dark:border-slate-800" data-testid="acceptance-evidence">
            <dt class="font-medium text-slate-600 dark:text-slate-400">{{ __('quotations.evidence.title') }}</dt>
            <dd class="text-slate-900 dark:text-slate-50">{{ $quotation->accepted_at->utc()->format('Y-m-d H:i:s') }} UTC</dd>
            <dt class="text-slate-600 dark:text-slate-400">IP</dt>
            <dd class="break-all text-slate-900 dark:text-slate-50">{{ $quotation->accepted_ip }}</dd>
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.evidence.user_agent') }}</dt>
            <dd class="break-all text-slate-900 dark:text-slate-50">{{ $quotation->accepted_user_agent ?? '-' }}</dd>
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.evidence.terms_version') }}</dt>
            <dd class="text-slate-900 dark:text-slate-50">{{ $quotation->terms_version ?? '-' }}</dd>
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.evidence.hash') }}</dt>
            <dd class="break-all font-mono text-slate-900 dark:text-slate-50">{{ $quotation->accepted_snapshot_hash ?? '-' }}</dd>
            <dt class="text-slate-600 dark:text-slate-400">{{ __('quotations.evidence.integrity') }}</dt>
            <dd class="text-slate-900 dark:text-slate-50">{{ $quotation->accepted_snapshot_hash === null ? '-' : ($quotation->hasIntactSnapshot() ? __('quotations.evidence.intact') : __('quotations.evidence.changed')) }}</dd>
        </dl>
    @endif
</div>
