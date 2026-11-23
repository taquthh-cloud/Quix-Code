<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 30)->unique(); // contoh: INV-2026-0001
            $table->string('type', 10); // dp|termin|final
            $table->char('currency', 3)->default('IDR');
            $table->unsignedBigInteger('amount'); // satuan terkecil
            $table->string('status', 25)->default('unpaid'); // InvoiceStatus
            $table->date('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable(); // manual_transfer|midtrans|...
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
