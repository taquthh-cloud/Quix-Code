<?php

namespace App\Enums;

enum TicketCategory: string
{
    case Revision = 'revision';
    case BugReport = 'bug_report';

    public function label(): string
    {
        return __("enums.ticket_category.{$this->value}");
    }
}
