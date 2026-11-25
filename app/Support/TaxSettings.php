<?php

namespace App\Support;

use App\Models\Setting;

/** Tarif PPN dari Pengaturan (kunci tax.rate_bp), dipakai builder quotation dan invoice manual. */
final class TaxSettings
{
    /** Tarif dalam basis point, 0 bila belum diatur; dibatasi 0-10.000. */
    public static function rateBp(): int
    {
        $value = Setting::query()->where('key', 'tax.rate_bp')->value('value');

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $bp = is_array($value) ? ($value['basis_points'] ?? $value['value'] ?? 0) : (int) $value;

        return max(0, min(10_000, (int) $bp));
    }
}
