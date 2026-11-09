<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unique(['team_id', 'code']);
        });

        Schema::create('journal_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('keyword', 80);
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->unique(['team_id', 'keyword']);
        });

        Schema::create('journal_template_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_account_id')->constrained()->restrictOnDelete();
            $table->enum('side', ['debit', 'credit']);
            $table->unique(['journal_template_id', 'side']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('request_id');
            $table->string('template_name');
            $table->date('date');
            $table->string('description', 500)->nullable();
            $table->timestamp('created_at');
            $table->unique(['team_id', 'request_id']);
            $table->index(['team_id', 'date', 'id']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_account_id')->constrained()->restrictOnDelete();
            $table->string('account_code', 30);
            $table->string('account_name');
            $table->enum('side', ['debit', 'credit']);
            $table->unsignedBigInteger('amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('journal_template_lines');
        Schema::dropIfExists('journal_templates');
        Schema::dropIfExists('journal_accounts');
    }
};
