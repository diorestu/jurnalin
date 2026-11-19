<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->string('code', 40);
            $t->string('name', 160);
            $t->string('category', 100)->nullable();
            $t->string('location', 160)->nullable();
            $t->date('acquired');
            $t->string('start_month', 7);
            $t->unsignedBigInteger('cost');
            $t->unsignedBigInteger('residual');
            $t->unsignedSmallInteger('months');
            foreach (['asset_account', 'expense_account', 'accumulation_account'] as $column) {
                $t->foreignId($column)->constrained('journal_accounts')->restrictOnDelete();
            }
            $t->unique(['team_id', 'code']);
        });
        Schema::create('asset_depreciations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('asset_id')->constrained('fixed_assets')->restrictOnDelete();
            $t->string('month', 7);
            $t->unsignedBigInteger('amount');
            $t->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->unique(['asset_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciations');
        Schema::dropIfExists('fixed_assets');
    }
};
