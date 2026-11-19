<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_document_lines', function (Blueprint $t) {
            $t->unsignedBigInteger('unit_discount')->default(0);
            $t->string('tax_code', 30)->nullable();
            $t->unsignedSmallInteger('tax_bps')->default(0);
            $t->boolean('tax_included')->default(false);
            $t->unsignedBigInteger('tax_amount')->default(0);
        });
        Schema::table('journal_entries', fn (Blueprint $t) => $t->string('cash_flow_category', 16)->nullable());
        foreach ([['1201', 'Uang Muka Pemasok', 'asset', 'supplier_advance'], ['1291', 'PPN Masukan', 'asset', 'vat_input'], ['2151', 'PPN Keluaran', 'liability', 'vat_output']] as [$code,$name,$type,$role]) {
            DB::table('journal_accounts')->where(compact('code', 'name', 'type'))->where('active', true)->where('is_cash', false)->whereNull('trade_role')->update(['trade_role' => $role]);
        }
    }

    public function down(): void
    {
        if (DB::table('trade_document_lines')->where('unit_discount', '>', 0)->orWhereNotNull('tax_code')->exists()
            || DB::table('trade_documents')->where('direction', 'pembelian')->where('kind', 'advance')->exists()
            || DB::table('journal_entries')->whereNotNull('cash_flow_category')->exists()) {
            throw new RuntimeException('Data pajak, uang muka atau klasifikasi laporan sudah digunakan.');
        }
        DB::table('journal_accounts')->whereIn('trade_role', ['supplier_advance', 'vat_input', 'vat_output'])->update(['trade_role' => null]);
        Schema::table('journal_entries', fn (Blueprint $t) => $t->dropColumn('cash_flow_category'));
        Schema::table('trade_document_lines', fn (Blueprint $t) => $t->dropColumn(['unit_discount', 'tax_code', 'tax_bps', 'tax_included', 'tax_amount']));
    }
};
