<?php

namespace App\Enums;

use InvalidArgumentException;

enum ProjectStatus: string
{
    case Lead = 'lead';
    case Quoted = 'quoted';
    case Negotiating = 'negotiating';
    case Deal = 'deal';
    case AwaitingDp = 'awaiting_dp';
    case Active = 'active';
    case AwaitingFinal = 'awaiting_final';
    case Handover = 'handover';
    case Warranty = 'warranty';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("enums.project_status.{$this->value}");
    }

    /**
     * Status yang boleh dituju dari status ini (Bagian 5 blueprint).
     * 'cancelled' bisa dari tahap mana pun kecuali sudah closed/cancelled.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        $next = match ($this) {
            self::Lead => [self::Quoted],
            self::Quoted => [self::Negotiating, self::Deal],
            self::Negotiating => [self::Deal],
            self::Deal => [self::AwaitingDp],
            self::AwaitingDp => [self::Active],
            self::Active => [self::AwaitingFinal],
            self::AwaitingFinal => [self::Handover],
            self::Handover => [self::Warranty],
            self::Warranty => [self::Closed],
            self::Closed, self::Cancelled => [],
        };

        if (! $this->isTerminal()) {
            $next[] = self::Cancelled;
        }

        return $next;
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Lempar exception jika lompat status; kembalikan status tujuan bila valid. */
    public function transitionTo(self $to): self
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidArgumentException(
                "Transisi status proyek tidak valid: {$this->value} → {$to->value}"
            );
        }

        return $to;
    }

    public function isTerminal(): bool
    {
        return $this === self::Closed || $this === self::Cancelled;
    }

    /** Status di mana staf boleh bekerja (geser milestone, balas tiket). */
    public function isWorkable(): bool
    {
        return in_array($this, [self::Active, self::AwaitingFinal, self::Warranty], true);
    }

    /** Warna untuk x-badge-status. */
    public function color(): string
    {
        return match ($this) {
            self::Lead, self::Quoted => 'slate',
            self::Negotiating, self::AwaitingDp, self::AwaitingFinal => 'amber',
            self::Deal, self::Handover => 'sky',
            self::Active, self::Warranty => 'emerald',
            self::Closed => 'zinc',
            self::Cancelled => 'red',
        };
    }
}
