<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

/** Invoice DP terbit otomatis saat klien menyetujui quotation; dikirim setelah transaksi DB selesai (T3-09). */
final class DownPaymentInvoiceIssued
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
