<?php

namespace App\Models;

use App\Enums\HandoverMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectHandover extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'delivery_method', 'payload', 'expires_at'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'delivery_method' => HandoverMethod::class,
            'payload' => 'encrypted',
            'expires_at' => 'datetime',
            'download_count' => 'integer',
            'downloaded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
