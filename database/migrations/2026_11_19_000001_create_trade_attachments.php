<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('trade_documents')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedInteger('size');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_attachments');
    }
};
