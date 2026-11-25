<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->char('preferred_currency', 3)->default('IDR');
            $table->softDeletes();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('on_hold_from', 24)->nullable();
            $table->string('on_hold_reason')->nullable();
            $table->string('cancelled_reason')->nullable();
            $table->string('lead_source', 100)->nullable();
            $table->json('utm')->nullable();
            $table->timestamp('handover_at')->nullable();
            $table->string('warranty_basis', 32)->nullable();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedSmallInteger('margin_bp')->default(0);
            $table->unsignedSmallInteger('tax_bp')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->string('terms_version', 40)->nullable();
            $table->string('accepted_user_agent', 512)->nullable();
            $table->char('accepted_snapshot_hash', 64)->nullable();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedSmallInteger('term_percent_bp')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
        });

        DB::table('invoices')->update([
            'subtotal' => DB::raw('amount'),
            'issued_at' => DB::raw('created_at'),
        ]);
        DB::table('invoices')->where('status', 'paid')->update(['paid_amount' => DB::raw('amount')]);
        DB::table('quotations')->update(['margin_bp' => DB::raw('ROUND(margin_percent * 100)')]);

        Schema::table('payments', function (Blueprint $table) {
            $table->string('reference_number', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('sender_name', 160)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('rejection_reason')->nullable();
        });

        Schema::table('revision_tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('revision_round')->default(0);
            $table->boolean('is_billable')->default(false);
            $table->timestamp('due_at')->nullable();
        });

        Schema::table('project_git_commits', function (Blueprint $table) {
            $table->string('branch', 255)->nullable();
            $table->foreignId('author_staff_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->boolean('signature_valid')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('purge_after')->nullable();
        });

        Schema::table('notifications_log', function (Blueprint $table) {
            $table->string('locale', 5)->default('id');
            $table->string('provider_message_id', 160)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->boolean('fallback_used')->default(false);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable();
            $table->char('checksum', 64)->nullable();
            $table->timestamp('scanned_at')->nullable();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('invoice_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->decimal('qty', 12, 3)->unsigned();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('amount');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['invoice_id', 'sort_order']);
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('project_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->restrictOnDelete();
            $table->string('from', 24);
            $table->string('to', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('version', 40);
            $table->string('ip', 45)->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'revoked_at']);
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100);
            $table->string('locale', 5);
            $table->string('channel', 24);
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['key', 'locale', 'channel', 'version'], 'notification_templates_version_unique');
        });

        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['project_id', 'created_at']);
        });

        Schema::create('change_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description');
            $table->string('status', 24)->default('requested');
            $table->foreignUlid('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('milestone_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milestone_id')->constrained('project_milestones')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->unique(['milestone_id', 'client_id']);
        });

        $this->backfillInvoiceSequences();
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_acceptances');
        Schema::dropIfExists('change_requests');
        Schema::dropIfExists('project_notes');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('project_status_histories');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('invoice_items');

        Schema::table('activity_logs', fn (Blueprint $table) => $table->dropColumn(['ip', 'user_agent']));
        Schema::table('files', fn (Blueprint $table) => $table->dropColumn(['original_name', 'checksum', 'scanned_at']));
        Schema::table('notifications_log', fn (Blueprint $table) => $table->dropColumn(['locale', 'provider_message_id', 'attempts', 'fallback_used']));
        Schema::table('webhook_deliveries', fn (Blueprint $table) => $table->dropColumn(['signature_valid', 'attempts', 'purge_after']));
        Schema::table('project_git_commits', function (Blueprint $table) {
            $table->dropForeign(['author_staff_id']);
            $table->dropColumn(['branch', 'author_staff_id']);
        });
        Schema::table('revision_tickets', fn (Blueprint $table) => $table->dropColumn(['revision_round', 'is_billable', 'due_at']));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['reference_number', 'bank_name', 'sender_name', 'paid_at', 'rejection_reason']));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['subtotal', 'discount_amount', 'tax_amount', 'paid_amount', 'term_percent_bp', 'issued_at', 'voided_at', 'void_reason']));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(['discount_amount', 'margin_bp', 'tax_bp', 'tax_amount', 'terms_version', 'accepted_user_agent', 'accepted_snapshot_hash']));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['on_hold_from', 'on_hold_reason', 'cancelled_reason', 'lead_source', 'utm', 'handover_at', 'warranty_basis']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'timezone', 'preferred_currency', 'deleted_at']));
    }

    private function backfillInvoiceSequences(): void
    {
        $sequences = [];

        foreach (DB::table('invoices')->pluck('invoice_number') as $number) {
            if (preg_match('/^(?:INV-(\d{4})-|QC\/(\d{4})\/)(\d+)$/', (string) $number, $match) !== 1) {
                continue;
            }

            $year = (int) (($match[1] ?? '') ?: ($match[2] ?? ''));
            $sequences[$year] = max($sequences[$year] ?? 0, (int) $match[3]);
        }

        foreach ($sequences as $year => $lastNumber) {
            DB::table('invoice_sequences')->insert([
                'year' => $year,
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
