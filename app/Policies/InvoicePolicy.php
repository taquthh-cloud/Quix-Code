<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

/** Staf tidak punya akses apa pun ke invoice. */
class InvoicePolicy
{
    use ChecksProjectAccess;

    public function viewAny(User $user, Project $project): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $project);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin()
            || ($invoice->status !== InvoiceStatus::Draft && $this->ownsProject($user, $invoice->project));
    }

    /** Unduh PDF invoice mengikuti aturan view. */
    public function download(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() && $invoice->status === InvoiceStatus::Draft;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin()
            && ! in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Cancelled, InvoiceStatus::Void], true);
    }

    /** Klien pemilik boleh mengunggah bukti bayar hanya selama invoice belum dibayar. */
    public function uploadProof(User $user, Invoice $invoice): bool
    {
        return $this->ownsProject($user, $invoice->project)
            && $invoice->status->acceptsPayment();
    }
}
