<?php

namespace App\Policies;

use App\Enums\FileVisibility;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use App\Policies\Concerns\ChecksProjectAccess;
use Illuminate\Support\Facades\Gate;

class FilePolicy
{
    use ChecksProjectAccess;

    public function view(User $user, ProjectFile $file): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($file->visibility === FileVisibility::Public) {
            return true;
        }

        if ($file->visibility !== FileVisibility::Client || ! $user->isClient()) {
            return false;
        }

        $project = Project::query()
            ->where('client_id', $user->id)
            ->find($file->project_id);

        return $project !== null && Gate::forUser($user)->allows('downloadHandover', $project);
    }

    public function delete(User $user, ProjectFile $file): bool
    {
        return $user->isAdmin();
    }
}
