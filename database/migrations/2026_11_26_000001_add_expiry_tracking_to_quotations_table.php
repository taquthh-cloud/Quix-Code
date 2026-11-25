<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** T3-06: penanda kapan quotation ditandai kedaluwarsa dan kapan pengingat H-3 terkirim (agar idempoten). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('reminded_at')->nullable();

            // Scheduler memindai status + valid_until setiap hari.
            $table->index(['status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropIndex(['status', 'valid_until']);
            $table->dropColumn(['expired_at', 'reminded_at']);
        });
    }
};
