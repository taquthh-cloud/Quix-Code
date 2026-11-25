<?php

namespace App\Events;

use App\Models\Quotation;
use Illuminate\Foundation\Events\Dispatchable;

/** Quotation terkirim akan habis dalam kurang dari atau sama dengan Quotation::REMINDER_DAYS hari (T3-06). */
final class QuotationExpiringSoon
{
    use Dispatchable;

    public function __construct(public readonly Quotation $quotation) {}
}
