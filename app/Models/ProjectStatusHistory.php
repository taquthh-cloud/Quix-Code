<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProjectStatusHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['project_id', 'from', 'to', 'actor_id', 'reason', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Project status history is append-only.'));
        static::deleting(fn () => throw new LogicException('Project status history is append-only.'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
