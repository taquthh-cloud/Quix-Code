<?php

namespace App\Policies;

use App\Enums\MilestoneStatus;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class MilestonePolicy
{
    use ChecksProjectAccess;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->canAccessProject($user, $project);
    }

    public function view(User $user, ProjectMilestone $milestone): bool
    {
        return $this->canAccessProject($user, $milestone->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, ProjectMilestone $milestone): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, ProjectMilestone $milestone): bool
    {
        return $user->isAdmin();
    }

    /**
     * T4-12. Hanya klien pemilik proyek yang menerima hasil, hanya untuk milestone berstatus selesai,
     * dan hanya sekali. Memakai relasi acceptances bila sudah dimuat agar daftar milestone tidak N+1.
     */
    public function accept(User $user, ProjectMilestone $milestone): bool
    {
        /** @var Project|null $project */
        $project = $milestone->project;
        $status = $milestone->getAttribute('status');
        $isDone = $status === MilestoneStatus::Done || $status === MilestoneStatus::Done->value;

        if (! $this->ownsProject($user, $project) || ! $isDone) {
            return false;
        }

        return $milestone->relationLoaded('acceptances')
            ? $milestone->acceptances->isEmpty()
            : ! $milestone->acceptances()->exists();
    }

    /** Geser/koreksi status: admin, atau staf yang ditugaskan di proyek DAN di milestone itu. */
    public function move(User $user, ProjectMilestone $milestone): bool
    {
        return $user->isAdmin()
            || ($this->isAssigned($user, $milestone->project)
                && (int) $milestone->assigned_to === (int) $user->id);
    }
}
