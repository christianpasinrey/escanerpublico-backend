<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subsidy_grants', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('BDNS')->index();
            $table->unsignedBigInteger('external_id')->comment('BDNS id de concesión');
            $table->string('cod_concesion', 50)->nullable()->index()->comment('BDNS codConcesion ej. SB150295544');
            $table->foreignId('call_id')->nullable()->constrained('subsidy_calls')->nullOnDelete();
            $table->unsignedBigInteger('external_call_id')->nullable()->index()->comment('BDNS idConvocatoria — para FK lazy si la convocatoria llega después');
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('beneficiario_raw', 500)->nullable()->comment('Texto original BDNS sin parsear');
            $table->string('beneficiario_nif', 20)->nullable()->index();
            $table->string('beneficiario_name', 500)->nullable();
            $table->date('grant_date')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable()->index();
            $table->decimal('ayuda_equivalente', 15, 2)->nullable();
            $table->text('instrumento')->nullable();
            $table->string('url_br', 500)->nullable()->comment('URL al PDF del boletín oficial');
            $table->boolean('tiene_proyecto')->default(false);
            $table->unsignedBigInteger('id_persona')->nullable()->comment('BDNS idPersona link interno');
            $table->date('fecha_alta')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->dateTime('ingested_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['company_id', 'grant_date']);
            $table->index(['organization_id', 'grant_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subsidy_grants');
    }
};
