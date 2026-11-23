<?php

namespace App\Enums;

/** Jenis perubahan penting yang dicatat di activity_logs (harga, status, invoice, tim). */
enum ActivityAction: string
{
    case ProjectCreated = 'project_created';
    case StatusChanged = 'status_changed';
    case ProgressChanged = 'progress_changed';
    case TicketStatusChanged = 'ticket_status_changed';
    case PriceChanged = 'price_changed';
    case StaffAssigned = 'staff_assigned';
    case StaffUnassigned = 'staff_unassigned';
    case UserCreated = 'user_created';
    case UserRoleChanged = 'user_role_changed';
    case UserTwoFactorReset = 'user_two_factor_reset';
    case SettingUpdated = 'setting_updated';
    case UserDeactivated = 'user_deactivated';
    case UserReactivated = 'user_reactivated';
    case ProjectNoteCreated = 'project_note_created';
    case InvoiceCreated = 'invoice_created';
    case InvoiceSent = 'invoice_sent';
    case InvoicePaid = 'invoice_paid';
    case InvoiceCancelled = 'invoice_cancelled';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';
    case QuotationSent = 'quotation_sent';
    case QuotationAccepted = 'quotation_accepted';
    case QuotationRejected = 'quotation_rejected';

    public function label(): string
    {
        return __("activity.actions.{$this->value}");
    }
}
