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
            $table->string('type', 20)->nullable();
        });

        foreach ([
            ['1101', 'Kas', 'asset'],
            ['1102', 'Bank BCA', 'asset'],
            ['4101', 'Pendapatan Usaha', 'revenue'],
            ['6102', 'Beban Listrik', 'expense'],
        ] as [$code, $name, $type]) {
            DB::table('journal_accounts')->where('code', $code)->where('name', $name)->update(['type' => $type]);
        }
    }

    public function down(): void
    {
        Schema::table('journal_accounts', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
