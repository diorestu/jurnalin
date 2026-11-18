<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('direction', 12);
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('contact')->nullable();
            $table->string('address')->nullable();
            $table->string('npwp', 32)->nullable();
            $table->unsignedSmallInteger('terms')->nullable();
            $table->unique(['team_id', 'direction', 'code']);
        });
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->constrained('trade_partners')->restrictOnDelete();
        });
        DB::table('trade_documents')->select('id', 'partner', 'team_id', 'direction')->chunkById(500, function ($docs) {
            foreach ($docs as $doc) {
                $query = DB::table('trade_partners')->where('team_id', $doc->team_id)->where('direction', $doc->direction)->where('name', $doc->partner);
                $id = $query->value('id') ?? DB::table('trade_partners')->insertGetId(['team_id' => $doc->team_id, 'direction' => $doc->direction, 'name' => $doc->partner, 'code' => 'MIG-'.$doc->id]);
                DB::table('trade_documents')->where('id', $doc->id)->update(['partner_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trade_documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('partner_id'));
        Schema::dropIfExists('trade_partners');
    }
};
