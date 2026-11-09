<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('description', 500)->nullable();
            $table->uuid('request_id');
            $table->string('request_hash', 64);
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('opening_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained()->restrictOnDelete();
            $table->string('sku', 80);
            $table->string('name', 160);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('total');
            $table->unique(['opening_balance_id', 'sku']);
        });
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->foreignId('opening_balance_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('opening_balances')->exists()) {
            throw new RuntimeException('Cannot roll back posted opening balances.');
        }
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opening_balance_id');
        });
        Schema::dropIfExists('opening_inventory');
        Schema::dropIfExists('opening_balances');
    }
};
