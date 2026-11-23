<?php

namespace App\Policies\Concerns;

use App\Models\Project;
use App\Models\User;

trait ChecksProjectAccess
{
    protected function ownsProject(User $user, ?Project $project): bool
    {
        return $project !== null
            && $user->isClient()
            && (int) $project->client_id === (int) $user->id;
    }

    protected function isAssigned(User $user, ?Project $project): bool
    {
        if ($project === null || ! $user->isStaff()) {
            return false;
        }

        if ($project->relationLoaded('assignments')) {
            return $project->assignments->contains(fn ($a) => (int) $a->staff_id === (int) $user->id);
        }

        return $project->assignments()->where('staff_id', $user->id)->exists();
    }

    /** Admin: semua; staf: yang ditugaskan; klien: miliknya. */
    protected function canAccessProject(User $user, ?Project $project): bool
    {
        return $user->isAdmin()
            || $this->ownsProject($user, $project)
            || $this->isAssigned($user, $project);
    }
}
