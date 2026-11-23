<?php

namespace App\Models;

use App\Enums\FileVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tabel `files` (dinamai ProjectFile agar tidak bentrok dengan class File milik Laravel). */
class ProjectFile extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'files';

    protected $fillable = ['project_id', 'uploaded_by', 'disk', 'path', 'mime', 'size', 'visibility'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visibility' => FileVisibility::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
