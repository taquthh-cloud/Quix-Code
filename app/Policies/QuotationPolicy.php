<?php

namespace App\Policies;

use App\Enums\QuotationStatus;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

/** Staf tidak punya akses apa pun ke quotation (memuat harga). */
class QuotationPolicy
{
    use ChecksProjectAccess;

    public function viewAny(User $user, Project $project): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $project);
    }

    public function view(User $user, Quotation $quotation): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Klien tidak boleh melihat draf.
        return $this->ownsProject($user, $quotation->project)
            && $quotation->status !== QuotationStatus::Draft;
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /** Hanya draf yang bisa diubah; yang sudah terkirim dibuatkan versi baru. */
    public function update(User $user, Quotation $quotation): bool
    {
        return $user->isAdmin() && $quotation->status === QuotationStatus::Draft;
    }

    public function send(User $user, Quotation $quotation): bool
    {
        return $user->isAdmin() && $quotation->status === QuotationStatus::Draft;
    }

    /** Klien menyetujui/menolak: hanya penawaran terkirim yang belum kedaluwarsa. */
    public function respond(User $user, Quotation $quotation): bool
    {
        if (! $this->ownsProject($user, $quotation->project) || $quotation->status !== QuotationStatus::Sent) {
            return false;
        }

        return $quotation->valid_until === null
            || ! $quotation->valid_until->copy()->endOfDay()->isPast();
    }
}
