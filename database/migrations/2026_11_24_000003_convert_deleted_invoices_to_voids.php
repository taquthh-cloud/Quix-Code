<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->whereNotNull('deleted_at')
            ->update([
                'status' => 'void',
                'voided_at' => DB::raw('deleted_at'),
                'void_reason' => 'Migrated from legacy soft deletion',
                'deleted_at' => null,
            ]);

    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('void_reason', 'Migrated from legacy soft deletion')
            ->update(['deleted_at' => DB::raw('voided_at')]);
    }
};
