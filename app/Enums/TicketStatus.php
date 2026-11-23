<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return __("enums.ticket_status.{$this->value}");
    }

    /** Kolom Kanban global, berurutan. */
    public static function kanbanColumns(): array
    {
        return [self::Open, self::InProgress, self::Resolved, self::Closed];
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'slate',
            self::InProgress => 'amber',
            self::Resolved => 'emerald',
            self::Closed => 'zinc',
        };
    }
}
