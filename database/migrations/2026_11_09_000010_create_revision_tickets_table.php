<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category', 20)->default('revision'); // revision|bug_report
            $table->string('priority', 10)->default('medium');   // low|medium|high
            $table->string('status', 20)->default('open');       // TicketStatus
            $table->unsignedInteger('sort_order')->default(0);   // urutan Kanban
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']); // Global Kanban
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_tickets');
    }
};
