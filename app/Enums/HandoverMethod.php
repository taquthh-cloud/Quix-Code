<?php

namespace App\Enums;

enum HandoverMethod: string
{
    case Download = 'download';
    case DiscordEmail = 'discord_email';
    case ManagedHosting = 'managed_hosting';

    public function label(): string
    {
        return __("enums.handover_method.{$this->value}");
    }
}
