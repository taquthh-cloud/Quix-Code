<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\DownPaymentInvoiceNotification;
use App\Notifications\QuotationExpiringNotification;
use App\Notifications\QuotationSentNotification;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Pengiriman email transaksional (T3-09). Setiap pengiriman dicatat di notifications_log, dan kegagalan
 * email tidak pernah menggagalkan alur utama (kirim quotation, persetujuan, scheduler).
 */
class Notifier
{
    public function quotationSent(Quotation $quotation): void
    {
        $project = $quotation->project()->with('client')->first();

        if ($project !== null) {
            $this->deliver($project->client, new QuotationSentNotification($quotation, $project), 'quotation_sent');
        }
    }

    public function quotationExpiring(Quotation $quotation): void
    {
        $project = $quotation->project()->with('client')->first();

        if ($project !== null) {
            $this->deliver($project->client, new QuotationExpiringNotification($quotation, $project), 'quotation_expiring');
        }
    }

    public function downPaymentInvoice(Invoice $invoice): void
    {
        $project = $invoice->project()->with('client')->first();

        if ($project !== null) {
            $this->deliver($project->client, new DownPaymentInvoiceNotification($invoice, $project), 'dp_invoice');
        }
    }

    /**
     * Kirim satu email dan catat hasilnya. Staf tidak pernah menerima email negosiasi atau tagihan.
     *
     * @return bool true bila terkirim
     */
    public function deliver(?User $user, Notification $notification, string $template): bool
    {
        if ($user === null || ! $user->is_active || $user->isStaff() || blank($user->email)) {
            return false;
        }

        $log = NotificationLog::create([
            'user_id' => $user->id,
            'channel' => 'email',
            'template' => $template,
            'status' => 'queued',
        ]);

        try {
            $user->notify($notification->locale($user->locale ?: config('app.locale')));

            $log->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

            return true;
        } catch (Throwable $e) {
            report($e);

            $log->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return false;
        }
    }
}
