<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Foundation\Events\Dispatchable;

/** Quotation terkirim yang melewati masa berlaku sudah ditandai 'expired' oleh scheduler (T3-06). */
final class QuotationExpired
{
    use Dispatchable;

    public function __construct(public readonly Quotation $quotation) {}
}
