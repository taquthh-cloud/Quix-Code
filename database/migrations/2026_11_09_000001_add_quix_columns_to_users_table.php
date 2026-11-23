<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->after('password')->index(); // UserRole: admin|staff|client
            $table->string('whatsapp', 20)->nullable()->after('role');
            $table->boolean('whatsapp_opt_in')->default(false)->after('whatsapp'); // WA hanya untuk klien yang setuju
            $table->string('locale', 5)->default('id')->after('whatsapp_opt_in'); // id|en
            $table->timestamp('last_login_at')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'whatsapp', 'whatsapp_opt_in', 'locale', 'last_login_at']);
        });
    }
};
