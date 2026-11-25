<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Foundation\Events\Dispatchable;

/** Admin mengirim quotation ke klien; dikirim setelah transaksi DB selesai (T3-09). */
final class QuotationSent
{
    use Dispatchable;

    public function __construct(public readonly Quotation $quotation) {}
}
