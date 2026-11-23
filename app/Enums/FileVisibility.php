<?php

namespace App\Enums;

enum FileVisibility: string
{
    case Private = 'private';
    case Client = 'client';
    case Public = 'public';

    public function label(): string
    {
        return __("enums.file_visibility.{$this->value}");
    }
}
