<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Skema termin (Bagian 5): 50-50 -> DP 50%, 30-40-30 -> DP 30%.
            $table->unsignedTinyInteger('dp_percent')->default(50)->after('margin_percent');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('dp_percent');
        });
    }
};
