<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectGitCommit extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id', 'commit_hash', 'commit_message', 'progress_recorded',
        'pushed_at', 'author_email', 'author_id',
    ];

    /** Hash & email tidak boleh tampil ke klien, jadi disembunyikan dari serialisasi. */
    protected $hidden = ['commit_hash', 'author_email'];

    protected function casts(): array
    {
        return [
            'progress_recorded' => 'integer',
            'pushed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
