<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->string('reversal_reason', 500)->nullable();
        });
        Schema::create('journal_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('mode', 20);
            $table->string('status', 20)->default('draft');
            $table->date('date');
            $table->string('description', 500)->nullable();
            $table->json('payload');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('request_hash', 64);
            $table->string('review_reason', 500)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'request_id']);
            $table->index(['team_id', 'status', 'date']);
        });
        Schema::create('accounting_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('month', 7);
            $table->boolean('is_locked');
            $table->string('reason', 500);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->unsignedInteger('version')->default(1);
            $table->unique(['team_id', 'month']);
        });
        Schema::create('accounting_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at');
            $table->index(['team_id', 'id']);
        });
    }

    public function down(): void
    {
        if (DB::table('journal_drafts')->exists() || DB::table('accounting_period_locks')->exists()
            || DB::table('accounting_audit_events')->exists() || DB::table('journal_entries')->whereNotNull('reversal_of_id')->exists()) {
            throw new RuntimeException('Cannot roll back accounting workflow records.');
        }
        Schema::dropIfExists('accounting_audit_events');
        Schema::dropIfExists('accounting_period_locks');
        Schema::dropIfExists('journal_drafts');
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn(['approved_at', 'reversal_reason']);
        });
    }
};
