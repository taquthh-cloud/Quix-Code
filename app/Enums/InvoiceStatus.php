<?php

namespace App\Enums;

/**
 * "Terlambat" (overdue) sengaja tidak disimpan: hitung dari due_date + status Unpaid
 * agar tidak perlu job penjadwal untuk menjaga konsistensi.
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case AwaitingVerification = 'awaiting_verification'; // bukti sudah diunggah, menunggu admin
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("enums.invoice_status.{$this->value}");
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    /** Klien masih boleh mengunggah bukti bayar. */
    public function acceptsPayment(): bool
    {
        return $this === self::Unpaid;
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'amber',
            self::AwaitingVerification => 'sky',
            self::Paid => 'emerald',
            self::Cancelled => 'zinc',
        };
    }
}
