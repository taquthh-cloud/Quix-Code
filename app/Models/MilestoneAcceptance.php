<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestoneAcceptance extends Model
{
    use HasFactory;

    protected $fillable = ['milestone_id', 'client_id', 'accepted_at', 'ip'];

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
