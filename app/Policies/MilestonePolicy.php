<?php

namespace App\Policies;

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

    /** Geser/koreksi status: admin, atau staf yang ditugaskan di proyek DAN di milestone itu. */
    public function move(User $user, ProjectMilestone $milestone): bool
    {
        return $user->isAdmin()
            || ($this->isAssigned($user, $milestone->project)
                && (int) $milestone->assigned_to === (int) $user->id);
    }
}
