<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Dp = 'dp';
    case Termin = 'termin';
    case Final = 'final';

    public function label(): string
    {
        return __("enums.invoice_type.{$this->value}");
    }
}
