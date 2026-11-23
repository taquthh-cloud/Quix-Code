<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->unique()->constrained()->cascadeOnDelete(); // 1 proyek = 1 handover
            $table->string('delivery_method', 20); // download|discord_email|managed_hosting
            $table->text('payload')->nullable();   // kredensial/link, cast: encrypted
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_handovers');
    }
};
