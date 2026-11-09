<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_accounts', function (Blueprint $table) {
            $table->boolean('is_cash')->default(false);
            $table->string('trade_role', 30)->nullable();
            $table->unique(['team_id', 'trade_role']);
        });

        DB::table('journal_accounts')->where('type', 'asset')->whereIn('code', ['1101', '1102'])
            ->update(['is_cash' => true]);

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('journal_template_id')->nullable()->change();
        });

        Schema::create('trade_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('direction', 12);
            $table->string('kind', 20);
            $table->foreignId('source_id')->nullable()->constrained('trade_documents')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('trade_documents')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('journal_accounts')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id');
            $table->string('request_hash', 64);
            $table->string('partner', 160);
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->unsignedBigInteger('total');
            $table->timestamp('created_at');
            $table->unique(['team_id', 'request_id']);
            $table->index(['team_id', 'direction', 'date', 'id']);
            $table->index(['source_id', 'kind']);
            $table->index(['order_id', 'kind']);
        });

        Schema::create('trade_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('trade_documents')->restrictOnDelete();
            $table->foreignId('source_line_id')->nullable()->constrained('trade_document_lines')->restrictOnDelete();
            $table->string('name', 160);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total');
            $table->index('document_id');
            $table->index('source_line_id');
        });

        Schema::create('trade_advance_allocations', function (Blueprint $table) {
            $table->foreignId('advance_id')->constrained('trade_documents')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('trade_documents')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->primary(['advance_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        if (DB::table('trade_documents')->exists() || DB::table('journal_entries')->whereNull('journal_template_id')->exists()) {
            throw new RuntimeException('Cannot roll back trade tables while transaction documents or their journals exist.');
        }

        Schema::dropIfExists('trade_advance_allocations');
        Schema::dropIfExists('trade_document_lines');
        Schema::dropIfExists('trade_documents');

        Schema::table('journal_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('journal_template_id')->change();
        });

        Schema::table('journal_accounts', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'trade_role']);
            $table->dropColumn(['is_cash', 'trade_role']);
        });
    }
};
