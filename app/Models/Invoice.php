<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['project_id', 'invoice_number', 'type', 'currency', 'amount', 'due_date', 'payment_method'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'currency' => Currency::class,
            'amount' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /** Terlambat dihitung, tidak disimpan. */
    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Unpaid
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /** Admin: semua; klien: invoice proyeknya; staf: tidak ada (level query). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Client => $query->whereIn($query->qualifyColumn('project_id'), Project::visibleIds($user)),
            UserRole::Staff => $query->whereRaw('0 = 1'),
        };
    }
}
