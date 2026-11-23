<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class ProjectPolicy
{
    use ChecksProjectAccess;

    /** Semua peran boleh membuka daftar; isinya dibatasi scope Project::visibleTo(). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $this->canAccessProject($user, $project);
    }

    /** Harga: admin dan klien pemilik saja. Staf tidak pernah. */
    public function viewPrice(User $user, Project $project): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function transition(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function assignStaff(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /** Atur webhook & repo GitHub. */
    public function manageIntegration(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function publishCaseStudy(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /** Hanya klien pemilik, dan hanya saat proyek berstatus handover. */
    public function chooseHandover(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project)
            && $project->status === ProjectStatus::Handover;
    }

    /** Arsip hanya bisa diunduh klien pemilik setelah invoice final lunas. */
    public function downloadHandover(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project) && $this->finalInvoiceSettled($project);
    }

    private function finalInvoiceSettled(Project $project): bool
    {
        $final = $project->invoices()->where('type', InvoiceType::Final->value);

        $hasPaid = (clone $final)
            ->where('status', InvoiceStatus::Paid->value)
            ->whereColumn('paid_amount', '>=', 'amount')
            ->exists();
        $hasOutstanding = (clone $final)->whereIn('status', [
            InvoiceStatus::Sent->value,
            InvoiceStatus::Unpaid->value,
            InvoiceStatus::AwaitingVerification->value,
        ])->exists();

        return $hasPaid && ! $hasOutstanding;
    }
}
