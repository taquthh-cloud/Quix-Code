<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_git_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->string('commit_hash', 64); // SHA-1 (40) / SHA-256 (64); tidak ditampilkan ke klien
            $table->text('commit_message');    // sudah tanpa tag [progress:X%], sudah dibatasi panjangnya
            $table->unsignedTinyInteger('progress_recorded')->nullable(); // nilai X dari tag
            $table->timestamp('pushed_at');
            $table->string('author_email')->nullable(); // internal saja
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete(); // dipetakan dari email -> nama staf
            $table->timestamps();

            $table->unique(['project_id', 'commit_hash']);
            $table->index(['project_id', 'pushed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_git_commits');
    }
};
