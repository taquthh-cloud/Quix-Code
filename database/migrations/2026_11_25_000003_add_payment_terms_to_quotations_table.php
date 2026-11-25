<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Skema termin: daftar basis point yang totalnya 10000 (mis. [3000,4000,3000]); divalidasi di PaymentTerms.
            $table->json('payment_terms')->nullable();
        });

        // Quotation lama: turunkan dari dp_percent (30 -> 30-40-30, selainnya 50-50).
        DB::table('quotations')->where('dp_percent', 30)->update(['payment_terms' => json_encode([3000, 4000, 3000])]);
        DB::table('quotations')->where(fn ($q) => $q->whereNull('dp_percent')->orWhere('dp_percent', '!=', 30))
            ->update(['payment_terms' => json_encode([5000, 5000])]);
    }

    public function down(): void
    {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('payment_terms'));
    }
};
