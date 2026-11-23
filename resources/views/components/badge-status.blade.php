@props(['status', 'label' => null])

@php
    $value = $status instanceof \BackedEnum ? (string) $status->value : (string) $status;

    $tone = match ($value) {
        'active', 'paid', 'resolved', 'accepted', 'done', 'verified' => 'success',
        'awaiting_dp', 'awaiting_final', 'awaiting_verification', 'unpaid', 'negotiating', 'open' => 'warning',
        'quoted', 'deal', 'sent', 'in_progress', 'handover', 'warranty' => 'info',
        'cancelled', 'rejected', 'failed', 'expired' => 'danger',
        default => 'neutral', // lead, draft, pending, closed, dll.
    };

    $classes = [
        'neutral' => 'bg-slate-100 text-slate-700 ring-slate-300 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
        'warning' => 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30',
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30',
        'danger' => 'bg-rose-50 text-rose-800 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
    ][$tone];

    $text = $label
        ?? (($status instanceof \BackedEnum && method_exists($status, 'label')) ? $status->label() : \Illuminate\Support\Str::headline($value));
@endphp

<span {{ $attributes->class(['inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    {{ $text }}
</span>
