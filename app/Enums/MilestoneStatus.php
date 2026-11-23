<?php

namespace App\Enums;

enum MilestoneStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return __("enums.milestone_status.{$this->value}");
    }
}
