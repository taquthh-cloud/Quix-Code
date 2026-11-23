<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->char('currency', 3)->default('IDR');
            $table->unsignedBigInteger('subtotal')->default(0);         // sum(items.amount)
            $table->unsignedBigInteger('operational_cost')->default(0);
            $table->decimal('margin_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('total')->default(0);
            $table->string('status', 20)->default('draft'); // draft|sent|accepted|rejected
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('accepted_ip', 45)->nullable(); // muat IPv6
            $table->timestamps();

            $table->unique(['project_id', 'version']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
