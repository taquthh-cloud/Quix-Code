<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->ulid('id')->primary(); // tampil di URL klien -> tidak berurutan
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('lead'); // ProjectStatus
            $table->unsignedTinyInteger('progress_percent')->default(0); // 0-100
            $table->string('github_repo')->nullable(); // contoh: quixcode/takipay
            $table->text('webhook_secret')->nullable(); // secret HMAC per repo (cast: encrypted)
            $table->char('currency', 3)->default('IDR'); // dikunci saat quotation disetujui
            $table->unsignedBigInteger('budget_reference')->nullable(); // satuan terkecil
            $table->unsignedBigInteger('final_price')->nullable(); // satuan terkecil
            $table->timestamp('warranty_ends_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
