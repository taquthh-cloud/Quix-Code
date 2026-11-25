<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_chats', function (Blueprint $table) {
            // Penanda digest email (T3-09): pesan yang sudah masuk satu email tidak diemail lagi.
            $table->timestamp('notified_at')->nullable()->after('read_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_chats', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
