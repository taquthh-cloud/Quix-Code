<?php

namespace App\Models;

use App\Enums\MilestoneStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectMilestone extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'title', 'target_progress', 'due_date', 'sort_order', 'assigned_to'];

    protected function casts(): array
    {
        return [
            'target_progress' => 'integer',
            'status' => MilestoneStatus::class,
            'due_date' => 'date',
            'sort_order' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function acceptances(): HasMany
    {
        return $this->hasMany(MilestoneAcceptance::class, 'milestone_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
