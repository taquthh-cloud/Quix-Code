<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\QuotationStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id', 'version', 'currency', 'subtotal', 'operational_cost',
        'margin_percent', 'total', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'currency' => Currency::class,
            'subtotal' => 'integer',
            'operational_cost' => 'integer',
            'margin_percent' => 'decimal:2',
            'total' => 'integer',
            'status' => QuotationStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function totalMoney(): Money
    {
        return Money::of($this->total, $this->currency);
    }

    /** Admin: semua; klien: penawaran proyeknya selain draf; staf: tidak ada. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Client => $query
                ->whereIn($query->qualifyColumn('project_id'), Project::visibleIds($user))
                ->where($query->qualifyColumn('status'), '!=', QuotationStatus::Draft->value),
            UserRole::Staff => $query->whereRaw('0 = 1'),
        };
    }
}
