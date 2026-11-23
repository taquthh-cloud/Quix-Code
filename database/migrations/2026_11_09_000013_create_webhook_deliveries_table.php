<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_id', 64)->unique(); // X-GitHub-Delivery -> idempotensi
            $table->string('event', 50);                 // push, ping, ...
            $table->string('status', 20)->default('received'); // received|processed|failed|ignored
            $table->json('payload');
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
