<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk', 20)->default('private');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size'); // byte
            $table->string('visibility', 20)->default('private'); // private|client|public
            $table->timestamps();

            $table->index(['project_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
