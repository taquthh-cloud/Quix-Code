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
    case AdminProjectAvailabilityChanged = 'admin_project_availability_changed';
    case ProjectNoteCreated = 'project_note_created';
    case InvoiceCreated = 'invoice_created';
    case InvoiceSent = 'invoice_sent';
    case InvoicePaid = 'invoice_paid';
    case InvoiceCancelled = 'invoice_cancelled';
    case PaymentSubmitted = 'payment_submitted';
    case PaymentVerified = 'payment_verified';
    case PaymentRejected = 'payment_rejected';
    case QuotationSent = 'quotation_sent';
    case QuotationAccepted = 'quotation_accepted';
    case QuotationRejected = 'quotation_rejected';
    case QuotationSuperseded = 'quotation_superseded';
    case QuotationExpired = 'quotation_expired';
    case QuotationReminded = 'quotation_reminded';
    case ClientPreviewStarted = 'client_preview_started';
    case ClientPreviewEnded = 'client_preview_ended';
    case MilestoneStatusChanged = 'milestone_status_changed';
    case MilestoneAccepted = 'milestone_accepted';

    public function label(): string
    {
        return __("activity.actions.{$this->value}");
    }
}
