<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->foreignId('account_id')->constrained('journal_accounts')->restrictOnDelete();
            $t->string('reference', 100);
            $t->date('date');
            $t->string('description')->nullable();
            $t->bigInteger('amount');
            $t->foreignId('matched_line_id')->nullable()->unique()->constrained('journal_entry_lines')->restrictOnDelete();
            $t->unique(['account_id', 'reference']);
            $t->index(['team_id', 'account_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
