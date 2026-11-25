<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderChat extends Model
{
    use HasFactory;

    /** Chat tidak diedit: hanya created_at. */
    public const UPDATED_AT = null;

    protected $fillable = ['project_id', 'sender_id', 'message'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'notified_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Chat negosiasi: admin semua, klien proyeknya, staf tidak ada. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Client => $query->whereIn($query->qualifyColumn('project_id'), Project::visibleIds($user)),
            UserRole::Staff => $query->whereRaw('0 = 1'),
        };
    }

    /** Pesan lawan bicara yang belum dibaca $user: hanya yang boleh dilihatnya, bukan pesannya sendiri. */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->visibleTo($user)
            ->whereNull($query->qualifyColumn('read_at'))
            ->where($query->qualifyColumn('sender_id'), '!=', $user->id);
    }
}
