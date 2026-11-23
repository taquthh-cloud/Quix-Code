<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\RevisionTicket;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

class TicketPolicy
{
    use ChecksProjectAccess;

    public function viewAny(User $user, Project $project): bool
    {
        return $this->canAccessProject($user, $project);
    }

    public function view(User $user, RevisionTicket $ticket): bool
    {
        return $this->canAccessProject($user, $ticket->project);
    }

    /** Buat tiket: admin dan klien pemilik. Staf tidak. */
    public function create(User $user, Project $project): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $project);
    }

    /** Balas tiket: admin, staf yang ditugaskan, klien pemilik. */
    public function comment(User $user, RevisionTicket $ticket): bool
    {
        return $this->canAccessProject($user, $ticket->project);
    }

    /** Ubah status/prioritas/urutan Kanban: admin dan staf yang ditugaskan. */
    public function updateStatus(User $user, RevisionTicket $ticket): bool
    {
        return $user->isAdmin() || $this->isAssigned($user, $ticket->project);
    }

    public function delete(User $user, RevisionTicket $ticket): bool
    {
        return $user->isAdmin();
    }
}
