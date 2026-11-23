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
        'margin_percent', 'dp_percent', 'total', 'valid_until',
    ];

    /** Pilihan DP: 50-50 atau 30-40-30 (Bagian 5 blueprint). */
    public const DP_OPTIONS = [30, 50];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'currency' => Currency::class,
            'subtotal' => 'integer',
            'operational_cost' => 'integer',
            'margin_percent' => 'decimal:2',
            'dp_percent' => 'integer',
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

    public function subtotalMoney(): Money
    {
        return Money::of($this->subtotal, $this->currency);
    }

    public function operationalMoney(): Money
    {
        return Money::of($this->operational_cost, $this->currency);
    }

    /** Margin dalam nilai uang: selisih total dengan subtotal + operasional (tidak disimpan terpisah). */
    public function marginMoney(): Money
    {
        return $this->totalMoney()->subtract($this->subtotalMoney())->subtract($this->operationalMoney());
    }

    /** Uang muka yang ditagihkan saat deal. */
    public function dpMoney(): Money
    {
        return $this->totalMoney()->percent($this->dp_percent ?? 50);
    }

    /** Kedaluwarsa setelah akhir hari valid_until. */
    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->copy()->endOfDay()->isPast();
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
