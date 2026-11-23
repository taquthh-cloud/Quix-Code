<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('target_progress'); // 0-100, otomatis selesai saat progres >= nilai ini
            $table->string('status', 20)->default('pending'); // pending|in_progress|done
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
            $table->index(['project_id', 'target_progress']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_milestones');
    }
};
