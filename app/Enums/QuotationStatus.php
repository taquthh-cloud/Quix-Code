<?php

namespace App\Enums;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return __("enums.quotation_status.{$this->value}");
    }
}
