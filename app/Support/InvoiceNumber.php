<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Nomor invoice berurutan per tahun: QC/2026/0001. */
final class InvoiceNumber
{
    public static function next(?CarbonInterface $at = null): string
    {
        $year = ($at ?? now())->year;

        return DB::transaction(function () use ($year): string {
            DB::table('invoice_sequences')->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
            ]);

            $sequence = DB::table('invoice_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $next = (int) $sequence->last_number + 1;

            DB::table('invoice_sequences')
                ->where('year', $year)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return 'QC/'.$year.'/'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }
}
