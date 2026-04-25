<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('summary_id')->constrained('boe_summaries')->cascadeOnDelete();
            $table->string('source', 20)->default('BOE')->index();
            $table->string('external_id', 50)->comment('BOE-A-YYYY-NNNN');
            $table->string('control', 50)->nullable();
            $table->string('seccion_code', 10)->nullable()->index();
            $table->string('seccion_nombre', 255)->nullable();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('departamento_code', 10)->nullable();
            $table->string('departamento_nombre', 500)->nullable();
            $table->string('epigrafe', 500)->nullable();
            $table->text('titulo')->nullable();
            $table->string('url_pdf', 500)->nullable();
            $table->unsignedBigInteger('pdf_size_bytes')->nullable();
            $table->string('pagina_inicial', 20)->nullable();
            $table->string('pagina_final', 20)->nullable();
            $table->string('url_html', 500)->nullable();
            $table->string('url_xml', 500)->nullable();
            $table->date('fecha_publicacion')->nullable()->index();
            $table->char('content_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->fullText('titulo', 'ft_titulo_items');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boe_items');
    }
};
