<?php

namespace App\Models;

use App\Enums\ActivityAction;
use App\Enums\Currency;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\UserRole;
use App\Support\ActivityLogger;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class Invoice extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['project_id', 'invoice_number', 'type', 'currency', 'amount', 'due_date', 'payment_method'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'currency' => Currency::class,
            'amount' => 'integer',
            'paid_amount' => 'integer',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'term_percent_bp' => 'integer',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Invoices are immutable financial records; void them instead of deleting them.');
    }

    public function void(?string $reason = null, ?User $actor = null): void
    {
        if (blank($reason)) {
            throw new InvalidArgumentException('A reason is required to void an invoice.');
        }

        DB::transaction(function () use ($reason, $actor): void {
            $invoice = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($invoice->status->isPaid()) {
                throw new LogicException('Paid invoices cannot be voided; issue a credit note instead.');
            }

            $previous = $invoice->status;
            $invoice->forceFill([
                'status' => InvoiceStatus::Void,
                'voided_at' => now(),
                'void_reason' => trim($reason),
            ])->save();

            ActivityLogger::log(ActivityAction::InvoiceCancelled, $invoice, [
                'old' => $previous->value,
                'new' => InvoiceStatus::Void->value,
                'reason' => trim($reason),
            ], $actor);

            $this->setRawAttributes($invoice->getAttributes(), true);
        });
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /** Terlambat dihitung, tidak disimpan. */
    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Sent, InvoiceStatus::Unpaid], true)
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /** Admin: semua; klien: invoice proyeknya; staf: tidak ada (level query). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            UserRole::Admin => $query,
            UserRole::Client => $query->whereIn($query->qualifyColumn('project_id'), Project::visibleIds($user))
                ->where($query->qualifyColumn('status'), '!=', InvoiceStatus::Draft->value),
            UserRole::Staff => $query->whereRaw('0 = 1'),
        };
    }
}
