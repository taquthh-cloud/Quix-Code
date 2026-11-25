<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Support\Money;
use App\Support\PaymentTerms;
use App\Support\QuotationCalculator;
use App\Support\QuotationSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id', 'version', 'currency', 'subtotal', 'operational_cost',
        'margin_percent', 'margin_bp', 'discount_amount', 'tax_bp', 'tax_amount',
        'dp_percent', 'payment_terms', 'total', 'valid_until',
    ];

    /** Pilihan DP: 50-50 atau 30-40-30 (Bagian 5 blueprint). */
    public const DP_OPTIONS = [30, 50];

    /** Pengingat dikirim saat sisa masa berlaku tinggal sekian hari atau kurang (T3-06). */
    public const REMINDER_DAYS = 3;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'currency' => Currency::class,
            'subtotal' => 'integer',
            'operational_cost' => 'integer',
            'margin_percent' => 'decimal:2',
            'dp_percent' => 'integer',
            'payment_terms' => 'array',
            'total' => 'integer',
            'discount_amount' => 'integer',
            'margin_bp' => 'integer',
            'tax_bp' => 'integer',
            'tax_amount' => 'integer',
            'status' => QuotationStatus::class,
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'expired_at' => 'datetime',
            'reminded_at' => 'datetime',
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

    public function discountMoney(): Money
    {
        return Money::of((int) $this->discount_amount, $this->currency);
    }

    public function taxMoney(): Money
    {
        return Money::of((int) $this->tax_amount, $this->currency);
    }

    /** Dasar pajak = total - PPN (basis + margin - diskon). */
    public function taxableMoney(): Money
    {
        return $this->totalMoney()->subtract($this->taxMoney());
    }

    /** Margin dalam nilai uang: dasar pajak + diskon - subtotal - operasional (tidak disimpan terpisah). */
    public function marginMoney(): Money
    {
        return $this->taxableMoney()
            ->add($this->discountMoney())
            ->subtract($this->subtotalMoney())
            ->subtract($this->operationalMoney());
    }

    /** Tarif PPN untuk tampilan, mis. "11" atau "11.5". */
    public function taxPercentLabel(): string
    {
        return rtrim(rtrim(QuotationCalculator::bpToPercent((int) $this->tax_bp), '0'), '.') ?: '0';
    }

    /**
     * Persen tiap termin dalam basis point. Quotation lama tanpa skema tersimpan
     * memakai preset dari dp_percent.
     *
     * @return list<int>
     */
    public function termBps(): array
    {
        $terms = $this->payment_terms;

        return is_array($terms) && PaymentTerms::isValid($terms)
            ? array_values($terms)
            : PaymentTerms::preset((int) ($this->dp_percent ?? 50));
    }

    /**
     * Nominal tiap termin; selisih pembulatan ada di termin terakhir.
     *
     * @return list<Money>
     */
    public function termMoney(): array
    {
        return PaymentTerms::allocate($this->totalMoney(), $this->termBps());
    }

    /**
     * Rincian tiap termin: subtotal, diskon, PPN, dan jumlah (T3-03).
     *
     * @return list<array{subtotal: Money, discount: Money, tax: Money, amount: Money}>
     */
    public function termBreakdown(): array
    {
        return PaymentTerms::allocateBreakdown(
            $this->totalMoney(),
            $this->discountMoney(),
            $this->taxMoney(),
            $this->termBps(),
        );
    }

    /** Uang muka yang ditagihkan saat deal = termin pertama. */
    public function dpMoney(): Money
    {
        return $this->termMoney()[0];
    }

    /** Isi quotation masih sama dengan yang disetujui klien (hash snapshot cocok). */
    public function hasIntactSnapshot(): bool
    {
        return $this->accepted_snapshot_hash !== null
            && hash_equals($this->accepted_snapshot_hash, QuotationSnapshot::hash($this->unsetRelation('items')));
    }

    /**
     * Kedaluwarsa: sudah ditandai 'expired' oleh scheduler, atau lewat akhir hari valid_until
     * (berlaku walau scheduler belum sempat berjalan, jadi tidak ada celah untuk menyetujui).
     */
    public function isExpired(): bool
    {
        return $this->status === QuotationStatus::Expired
            || ($this->valid_until !== null && $this->valid_until->copy()->endOfDay()->isPast());
    }

    /** Sisa hari sampai valid_until (0 = berakhir hari ini, negatif = sudah lewat); null bila tanpa batas. */
    public function daysLeft(): ?int
    {
        if ($this->valid_until === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->valid_until->copy()->startOfDay(), false);
    }

    /** Penawaran terkirim yang masa berlakunya tinggal REMINDER_DAYS hari atau kurang. */
    public function isExpiringSoon(): bool
    {
        $left = $this->daysLeft();

        return $this->status === QuotationStatus::Sent
            && $left !== null
            && $left >= 0
            && $left <= self::REMINDER_DAYS;
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
