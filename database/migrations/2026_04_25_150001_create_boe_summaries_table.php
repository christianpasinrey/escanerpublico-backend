<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boe_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('BOE')->index();
            $table->string('identificador', 50)->comment('BOE-S-YYYY-NNN');
            $table->date('fecha_publicacion')->index();
            $table->string('numero', 10)->nullable();
            $table->string('url_pdf', 500)->nullable();
            $table->unsignedBigInteger('pdf_size_bytes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->dateTime('ingested_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'identificador']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boe_summaries');
    }
};
