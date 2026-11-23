<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_progress_percent_range CHECK (progress_percent BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_paid_amount_within_total CHECK (paid_amount <= amount)');
        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_positive_qty CHECK (qty > 0)');
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_tax_bp_range CHECK (tax_bp <= 10000)');
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            return;
        }

        $dropKeyword = $driver === 'mysql' ? 'DROP CHECK' : 'DROP CONSTRAINT';

        DB::statement("ALTER TABLE quotations {$dropKeyword} quotations_tax_bp_range");
        DB::statement("ALTER TABLE invoice_items {$dropKeyword} invoice_items_positive_qty");
        DB::statement("ALTER TABLE invoices {$dropKeyword} invoices_paid_amount_within_total");
        DB::statement("ALTER TABLE projects {$dropKeyword} projects_progress_percent_range");
    }
};
