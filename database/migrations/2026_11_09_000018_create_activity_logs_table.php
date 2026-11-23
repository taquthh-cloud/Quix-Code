<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = sistem/webhook
            $table->string('subject_type');
            $table->string('subject_id', 36); // string: subjek bisa bigint atau ULID
            $table->string('action', 50);     // price_changed, status_changed, invoice_paid, ...
            $table->json('properties')->nullable(); // {"old": ..., "new": ...}
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
