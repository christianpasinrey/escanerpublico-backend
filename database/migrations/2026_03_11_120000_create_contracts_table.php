<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();

            // Identificación
            $table->string('external_id')->unique()->comment('ID entrada PLACSP');
            $table->string('expediente')->index()->comment('ContractFolderID');
            $table->string('link')->nullable()->comment('URL ficha PLACSP');

            // Estado
            $table->string('status_code', 10)->index()->comment('PRE,PUB,EV,ADJ,RES,ANUL');

            // Órgano de contratación
            $table->string('organo_contratante');
            $table->string('organo_dir3')->nullable()->index()->comment('Código DIR3');
            $table->string('organo_superior')->nullable();

            // Objeto del contrato
            $table->text('objeto')->comment('Nombre/descripción del contrato');
            $table->string('tipo_contrato_code', 10)->nullable()->index()->comment('1=Obras,2=Servicios,3=Suministros...');
            $table->string('subtipo_contrato_code', 10)->nullable();

            // Importes (céntimos no, decimales)
            $table->decimal('importe_sin_iva', 15, 2)->nullable();
            $table->decimal('importe_con_iva', 15, 2)->nullable();
            $table->decimal('valor_estimado', 15, 2)->nullable();

            // Procedimiento
            $table->string('procedimiento_code', 10)->nullable()->index()->comment('1=Abierto,2=Restringido...');
            $table->string('urgencia_code', 10)->nullable();

            // CPV
            $table->json('cpv_codes')->nullable()->comment('Array de códigos CPV');

            // Ubicación
            $table->string('comunidad_autonoma')->nullable();
            $table->string('nuts_code', 10)->nullable()->index();
            $table->string('lugar_ejecucion')->nullable();

            // Plazos
            $table->date('fecha_presentacion_limite')->nullable();
            $table->decimal('duracion', 8, 2)->nullable();
            $table->string('duracion_unidad', 5)->nullable()->comment('ANN,MON,DAY');
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            // Resultado / Adjudicación
            $table->string('resultado_code', 10)->nullable()->index();
            $table->date('fecha_adjudicacion')->nullable();
            $table->integer('num_ofertas')->nullable();
            $table->string('adjudicatario_nombre')->nullable();
            $table->string('adjudicatario_nif')->nullable()->index();
            $table->decimal('importe_adjudicacion_sin_iva', 15, 2)->nullable();
            $table->decimal('importe_adjudicacion_con_iva', 15, 2)->nullable();
            $table->date('fecha_formalizacion')->nullable();

            // Metadatos
            $table->timestamp('synced_at')->nullable()->comment('Última sincronización del feed');
            $table->timestamps();

            // Índices compuestos
            $table->index(['tipo_contrato_code', 'status_code']);
            $table->index(['fecha_adjudicacion']);
            $table->index('importe_con_iva');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
