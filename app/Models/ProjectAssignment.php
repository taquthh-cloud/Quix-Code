<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ProjectAssignment extends Pivot
{
    protected $table = 'project_assignments';

    public $incrementing = true;

    protected $fillable = ['project_id', 'staff_id', 'task_role'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
