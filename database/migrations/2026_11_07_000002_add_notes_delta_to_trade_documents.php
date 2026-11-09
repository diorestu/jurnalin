<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->json('notes_delta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trade_documents', function (Blueprint $table) {
            $table->dropColumn('notes_delta');
        });
    }
};
