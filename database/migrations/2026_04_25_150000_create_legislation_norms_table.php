<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legislation_norms', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('BOE')->index();
            $table->string('external_id', 50)->comment('BOE-A-YYYY-NNNN');
            $table->string('ambito_code', 10)->nullable()->index();
            $table->string('ambito_text', 100)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('departamento_code', 10)->nullable();
            $table->string('departamento_text', 255)->nullable();
            $table->string('rango_code', 10)->nullable()->index();
            $table->string('rango_text', 100)->nullable();
            $table->string('numero_oficial', 50)->nullable();
            $table->text('titulo')->nullable();
            $table->date('fecha_disposicion')->nullable();
            $table->date('fecha_publicacion')->nullable()->index();
            $table->date('fecha_vigencia')->nullable();
            $table->dateTime('fecha_actualizacion')->nullable();
            $table->boolean('vigencia_agotada')->default(false)->index();
            $table->string('estado_consolidacion_code', 10)->nullable();
            $table->string('estado_consolidacion_text', 100)->nullable();
            $table->string('url_eli', 500)->nullable();
            $table->string('url_html_consolidada', 500)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->dateTime('ingested_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->fullText('titulo', 'ft_titulo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legislation_norms');
    }
};
