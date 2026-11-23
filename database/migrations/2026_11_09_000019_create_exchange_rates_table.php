<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('base_currency', 3);
            $table->char('target_currency', 3);
            $table->decimal('rate', 18, 8); // hanya untuk estimasi tampilan
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['base_currency', 'target_currency', 'fetched_at'], 'exchange_rates_pair_fetched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
