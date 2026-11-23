<?php

namespace App\Enums;

/** Jenis perubahan penting yang dicatat di activity_logs (harga, status, invoice, tim). */
enum ActivityAction: string
{
    case ProjectCreated = 'project_created';
    case StatusChanged = 'status_changed';
    case ProgressChanged = 'progress_changed';
    case PriceChanged = 'price_changed';
    case StaffAssigned = 'staff_assigned';
    case StaffUnassigned = 'staff_unassigned';
    case InvoiceCreated = 'invoice_created';
    case InvoicePaid = 'invoice_paid';
    case InvoiceCancelled = 'invoice_cancelled';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';

    public function label(): string
    {
        return __("activity.actions.{$this->value}");
    }
}
