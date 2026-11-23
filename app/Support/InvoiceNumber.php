<?php

namespace App\Support;

use App\Models\Invoice;
use Carbon\CarbonInterface;

/** Nomor invoice berurutan per tahun: INV-2026-0001. Panggil di dalam transaksi. */
final class InvoiceNumber
{
    public static function next(?CarbonInterface $at = null): string
    {
        $prefix = 'INV-'.($at ?? now())->year.'-';

        // Termasuk invoice yang di-soft-delete agar nomor tidak pernah dipakai ulang.
        $last = Invoice::withTrashed()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByRaw('length(invoice_number) desc')
            ->orderByDesc('invoice_number')
            ->lockForUpdate()
            ->value('invoice_number');

        $sequence = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
