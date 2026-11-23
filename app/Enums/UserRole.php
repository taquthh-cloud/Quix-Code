<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Client = 'client';

    public function label(): string
    {
        return __("enums.user_role.{$this->value}");
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isStaff(): bool
    {
        return $this === self::Staff;
    }

    public function isClient(): bool
    {
        return $this === self::Client;
    }

    /** Admin & staff memakai area kerja gelap, klien memakai portal terang. */
    public function isInternal(): bool
    {
        return $this !== self::Client;
    }

    /** Nama route dasbor per peran (sesuaikan saat route dibuat di Tahap 2 & 4). */
    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin, self::Staff => 'admin.dashboard',
            self::Client => 'portal.overview',
        };
    }
}
