<?php

namespace App\Enums;

/**
 * "Terlambat" (overdue) sengaja tidak disimpan: hitung dari due_date + status Unpaid
 * agar tidak perlu job penjadwal untuk menjaga konsistensi.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Unpaid = 'unpaid';
    case AwaitingVerification = 'awaiting_verification'; // bukti sudah diunggah, menunggu admin
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Void = 'void';

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
        return in_array($this, [self::Sent, self::Unpaid], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Unpaid, self::Sent => 'amber',
            self::AwaitingVerification => 'sky',
            self::Paid => 'emerald',
            self::Cancelled, self::Void => 'zinc',
        };
    }
}
