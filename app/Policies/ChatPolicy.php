<?php

namespace App\Policies;

use App\Models\OrderChat;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;

/** Chat negosiasi membahas harga: hanya admin dan klien pemilik. Staf tidak pernah. */
class ChatPolicy
{
    use ChecksProjectAccess;

    public function viewAny(User $user, Project $project): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $project);
    }

    public function view(User $user, OrderChat $chat): bool
    {
        return $user->isAdmin() || $this->ownsProject($user, $chat->project);
    }

    /** Tidak bisa mengirim pesan ke proyek yang sudah closed/cancelled. */
    public function create(User $user, Project $project): bool
    {
        return ($user->isAdmin() || $this->ownsProject($user, $project))
            && ! $project->status->isTerminal();
    }
}
