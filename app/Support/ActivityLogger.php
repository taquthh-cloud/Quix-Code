<?php

namespace App\Support;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Models\User;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat perubahan penting (harga, status, invoice). Tanpa user login,
 * baris dicatat sebagai aksi sistem (user_id = null), misalnya dari webhook.
 */
class ActivityLogger
{
    /** @param array<string, mixed> $properties */
    public static function log(ActivityAction $action, Model $subject, array $properties = [], ?User $actor = null): ActivityLog
    {
        $actor ??= auth()->user();

        return ActivityLog::create([
            'user_id' => $actor?->getKey(),
            'subject_type' => $subject::class,
            'subject_id' => (string) $subject->getKey(),
            'action' => $action->value,
            'properties' => $properties ?: null,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /** Catat perubahan nilai: {"old": ..., "new": ...} (enum disimpan sebagai nilainya). */
    public static function changed(ActivityAction $action, Model $subject, mixed $old, mixed $new, ?User $actor = null): ActivityLog
    {
        return self::log($action, $subject, [
            'old' => $old instanceof BackedEnum ? $old->value : $old,
            'new' => $new instanceof BackedEnum ? $new->value : $new,
        ], $actor);
    }
}
