<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->string('sku', 80);
            $t->string('name', 160);
            $t->string('unit', 30);
            $t->boolean('stock');
            $t->unsignedBigInteger('buy_price');
            $t->unsignedBigInteger('sell_price');
            foreach (['sales_account', 'expense_account'] as $key) {
                $t->foreignId($key)->nullable()->constrained('journal_accounts')->restrictOnDelete();
            }
            $t->unique(['team_id', 'sku']);
        });
        Schema::table('trade_document_lines', function (Blueprint $t) {
            $t->foreignId('item_id')->nullable()->constrained('catalog_items')->restrictOnDelete();
            $t->unsignedBigInteger('stock_cost')->default(0);
            foreach (['posting_account', 'cost_account'] as $col) {
                $t->foreignId($col)->nullable()->constrained('journal_accounts')->restrictOnDelete();
            }
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('item_id')->constrained('catalog_items')->restrictOnDelete();
            $t->foreignId('line_id')->nullable()->unique()->constrained('trade_document_lines')->restrictOnDelete();
            $t->date('date');
            $t->bigInteger('quantity');
            $t->bigInteger('value');
            $t->string('reason', 32);
            $t->index(['item_id', 'date']);
        });
        DB::table('opening_inventory as i')->join('opening_balances as o', 'o.id', '=', 'i.opening_balance_id')->select('i.*', 'o.team_id', 'o.date')->orderBy('i.id')->each(function ($row) {
            $id = DB::table('catalog_items')->insertGetId(['team_id' => $row->team_id, 'sku' => strtoupper($row->sku), 'name' => $row->name, 'unit' => 'unit', 'stock' => true, 'buy_price' => $row->unit_cost, 'sell_price' => 0]);
            DB::table('stock_movements')->insert(['item_id' => $id, 'date' => $row->date, 'quantity' => $row->quantity, 'value' => $row->total, 'reason' => 'Saldo awal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::table('trade_document_lines', function (Blueprint $t) {
            foreach (['item_id', 'posting_account', 'cost_account'] as $col) {
                $t->dropConstrainedForeignId($col);
            } $t->dropColumn('stock_cost');
        });
        Schema::dropIfExists('catalog_items');
    }
};
