<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_studies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->unique()->constrained()->restrictOnDelete(); // 1 proyek = 1 case study
            $table->string('slug')->unique();
            $table->json('title');      // {"id": "...", "en": "..."}
            $table->json('summary');    // {"id": "...", "en": "..."}
            $table->json('tech_stack')->nullable(); // ["Laravel","Livewire",...]
            $table->string('cover_path')->nullable(); // disk publik
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_studies');
    }
};
